#include <libavformat/avformat.h>
#include <libavcodec/avcodec.h>
#include <libavutil/imgutils.h>
#include <libavutil/opt.h>
#include <stdio.h>
#include <stdlib.h>
#include <float.h>
#include <string.h>

// Function Prototypes
void open_input_file(const char *filename, AVFormatContext **fmt_ctx);
void find_video_stream(AVFormatContext *fmt_ctx, int *video_stream_idx, AVCodecContext **video_dec_ctx, AVStream **video_stream);
void process_keyframes(AVFormatContext *fmt_ctx, AVCodecContext *video_dec_ctx, AVStream *video_stream, double start_position, double end_position, int limit, const char *output_dir);
void save_frame_as_jpeg(AVFrame *frame, int width, int height, int frame_index, const char *output_dir, double timestamp, FILE *json_file);
void cleanup(AVFormatContext *fmt_ctx, AVCodecContext *video_dec_ctx);

// Main Function
int main(int argc, char **argv) {
    if (argc < 2) {
        fprintf(stderr, "Usage: %s <video file> [start position] [end position] [limit] [output directory]\n", argv[0]);
        exit(EXIT_FAILURE);
    }

    const char *filename = argv[1];
    double start_position = (argc > 2) ? atof(argv[2]) : 0;
    double end_position = (argc > 3) ? atof(argv[3]) : DBL_MAX;
    int limit = (argc > 4) ? atoi(argv[4]) : INT_MAX;
    const char *output_dir = (argc > 5) ? argv[5] : ".";

    avformat_network_init(); // Initialize libavformat and network components

    AVFormatContext *fmt_ctx = NULL;
    AVCodecContext *video_dec_ctx = NULL;
    AVStream *video_stream = NULL;
    int video_stream_idx = -1;

    open_input_file(filename, &fmt_ctx);
    find_video_stream(fmt_ctx, &video_stream_idx, &video_dec_ctx, &video_stream);
    process_keyframes(fmt_ctx, video_dec_ctx, video_stream, start_position, end_position, limit, output_dir);
    cleanup(fmt_ctx, video_dec_ctx);

    return 0;
}

// Function Implementations
void open_input_file(const char *filename, AVFormatContext **fmt_ctx) {
    if (avformat_open_input(fmt_ctx, filename, NULL, NULL) < 0) {
        fprintf(stderr, "Could not open source file %s\n", filename);
        exit(EXIT_FAILURE);
    }

    if (avformat_find_stream_info(*fmt_ctx, NULL) < 0) {
        fprintf(stderr, "Could not find stream information\n");
        exit(EXIT_FAILURE);
    }
}

void find_video_stream(AVFormatContext *fmt_ctx, int *video_stream_idx, AVCodecContext **video_dec_ctx, AVStream **video_stream) {
    const AVCodec *dec = NULL;
    int ret = av_find_best_stream(fmt_ctx, AVMEDIA_TYPE_VIDEO, -1, -1, &dec, 0);
    if (ret < 0) {
        fprintf(stderr, "Could not find video stream in input file\n");
        exit(EXIT_FAILURE);
    }
    *video_stream_idx = ret;
    *video_stream = fmt_ctx->streams[*video_stream_idx];

    *video_dec_ctx = avcodec_alloc_context3(dec);
    if (!*video_dec_ctx) {
        fprintf(stderr, "Failed to allocate the video codec context\n");
        exit(EXIT_FAILURE);
    }

    ret = avcodec_parameters_to_context(*video_dec_ctx, (*video_stream)->codecpar);
    if (ret < 0) {
        fprintf(stderr, "Failed to copy codec parameters to context\n");
        exit(EXIT_FAILURE);
    }

    if ((ret = avcodec_open2(*video_dec_ctx, dec, NULL)) < 0) {
        fprintf(stderr, "Failed to open codec for stream #%u\n", *video_stream_idx);
        exit(EXIT_FAILURE);
    }
}

void process_keyframes(AVFormatContext *fmt_ctx, AVCodecContext *video_dec_ctx, AVStream *video_stream, double start_position, double end_position, int limit, const char *output_dir) {
    AVPacket packet;
    AVFrame *frame = av_frame_alloc();
    if (!frame) {
        fprintf(stderr, "Could not allocate frame\n");
        exit(EXIT_FAILURE);
    }

    int frame_count = 0;
    double packet_dts_time;
    int ret;

    char json_filename[1024];
    snprintf(json_filename, sizeof(json_filename), "%s/index.json", output_dir);
    FILE *json_file = fopen(json_filename, "w");
    if (!json_file) {
        fprintf(stderr, "Could not open %s for writing\n", json_filename);
        exit(EXIT_FAILURE);
    }
    fprintf(json_file, "{\n");

    while (av_read_frame(fmt_ctx, &packet) >= 0) {
        if (packet.stream_index == video_stream->index) {
            packet_dts_time = packet.dts * av_q2d(video_stream->time_base);
            if (packet_dts_time >= start_position && packet_dts_time <= end_position) {
                if (packet.flags & AV_PKT_FLAG_KEY) {
                    ret = avcodec_send_packet(video_dec_ctx, &packet);
                    if (ret < 0) {
                        fprintf(stderr, "Error sending packet for decoding: %s\n", av_err2str(ret));
                        continue;
                    }

                    while (ret >= 0) {
                        ret = avcodec_receive_frame(video_dec_ctx, frame);
                        if (ret == AVERROR(EAGAIN) || ret == AVERROR_EOF) {
                            break;
                        } else if (ret < 0) {
                            fprintf(stderr, "Error during decoding: %s\n", av_err2str(ret));
                            exit(EXIT_FAILURE);
                        }

                        save_frame_as_jpeg(frame, video_dec_ctx->width, video_dec_ctx->height, frame_count, output_dir, packet_dts_time, json_file);
                        frame_count++;
                        if (frame_count >= limit) {
                            break;
                        }
                    }
                }
            }
        }
        av_packet_unref(&packet);
        if (frame_count >= limit) {
            break;
        }
    }

    fprintf(json_file, "\n}\n");
    fclose(json_file);

    av_frame_free(&frame);
}

void save_frame_as_jpeg(AVFrame *frame, int width, int height, int frame_index, const char *output_dir, double timestamp, FILE *json_file) {
    AVCodecContext *jpeg_ctx = NULL;
    const AVCodec *jpeg_codec = NULL;
    AVPacket packet;
    int ret;

    jpeg_codec = avcodec_find_encoder(AV_CODEC_ID_MJPEG);
    if (!jpeg_codec) {
        fprintf(stderr, "Codec not found\n");
        exit(EXIT_FAILURE);
    }

    jpeg_ctx = avcodec_alloc_context3(jpeg_codec);
    if (!jpeg_ctx) {
        fprintf(stderr, "Could not allocate video codec context\n");
        exit(EXIT_FAILURE);
    }

    jpeg_ctx->pix_fmt = AV_PIX_FMT_YUVJ420P;
    jpeg_ctx->height = height;
    jpeg_ctx->width = width;
    jpeg_ctx->time_base = (AVRational){1, 25};

    if (avcodec_open2(jpeg_ctx, jpeg_codec, NULL) < 0) {
        fprintf(stderr, "Could not open codec\n");
        exit(EXIT_FAILURE);
    }

    // Convert frame to YUVJ420P format
    AVFrame *yuv_frame = av_frame_alloc();
    if (!yuv_frame) {
        fprintf(stderr, "Could not allocate YUV frame\n");
        exit(EXIT_FAILURE);
    }

    yuv_frame->format = AV_PIX_FMT_YUVJ420P;
    yuv_frame->width = width;
    yuv_frame->height = height;
    ret = av_frame_get_buffer(yuv_frame, 32);
    if (ret < 0) {
        fprintf(stderr, "Could not allocate YUV frame buffer\n");
        exit(EXIT_FAILURE);
    }

    ret = av_frame_make_writable(yuv_frame);
    if (ret < 0) {
        fprintf(stderr, "Could not make YUV frame writable\n");
        exit(EXIT_FAILURE);
    }

    av_image_copy(yuv_frame->data, yuv_frame->linesize, (const uint8_t **)(frame->data), frame->linesize, frame->format, width, height);

    // Initialize packet
    av_init_packet(&packet);
    packet.data = NULL;
    packet.size = 0;

    ret = avcodec_send_frame(jpeg_ctx, yuv_frame);
    if (ret < 0) {
        fprintf(stderr, "Error sending a frame for encoding: %s\n", av_err2str(ret));
        exit(EXIT_FAILURE);
    }

    ret = avcodec_receive_packet(jpeg_ctx, &packet);
    if (ret < 0) {
        fprintf(stderr, "Error during encoding: %s\n", av_err2str(ret));
        exit(EXIT_FAILURE);
    }

    char filename[1024];
    snprintf(filename, sizeof(filename), "%s/frame-%04d.jpg", output_dir, frame_index);

    FILE *jpeg_file = fopen(filename, "wb");
    if (!jpeg_file) {
        fprintf(stderr, "Could not open %s\n", filename);
        exit(EXIT_FAILURE);
    }
    fwrite(packet.data, 1, packet.size, jpeg_file);
    fclose(jpeg_file);

    // Write JSON entry
    if (frame_index > 0) {
        fprintf(json_file, ",\n");
    }
    fprintf(json_file, "    \"%.3f\": \"%s/frame-%04d.jpg\"", timestamp, output_dir, frame_index);

    av_packet_unref(&packet);
    avcodec_free_context(&jpeg_ctx);
    av_frame_free(&yuv_frame);
}

void cleanup(AVFormatContext *fmt_ctx, AVCodecContext *video_dec_ctx) {
    avcodec_free_context(&video_dec_ctx);
    avformat_close_input(&fmt_ctx);
}
