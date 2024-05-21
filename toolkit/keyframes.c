#include <libavformat/avformat.h>
#include <libavcodec/avcodec.h>
#include <libavutil/imgutils.h>
#include <libavutil/opt.h>
#include <libavutil/error.h>
#include <stdio.h>
#include <stdlib.h>
#include <float.h>
#include <string.h>
#include <getopt.h>

// Function Prototypes
void open_input_file(const char *filename, AVFormatContext **fmt_ctx);
void find_video_stream(AVFormatContext *fmt_ctx, int *video_stream_idx, AVCodecContext **video_dec_ctx, AVStream **video_stream);
void process_keyframes(AVFormatContext *fmt_ctx, AVCodecContext *video_dec_ctx, AVStream *video_stream, double start_position, double end_position, int limit, const char *output_dir, int create_jpeg, int create_index);
void save_frame_as_jpeg(AVFrame *frame, int width, int height, int frame_index, const char *output_dir);
void cleanup(AVFormatContext *fmt_ctx, AVCodecContext *video_dec_ctx);

void print_usage(const char *prog_name) {
    fprintf(stderr, "Usage: %s -f <video file> [-s start position] [-e end position] [-l limit] [-d output directory] [-j (create jpegs)] [-i (create index json)]\n", prog_name);
}
int main(int argc, char **argv) {
    const char *filename = NULL;
    double start_position = 0;
    double end_position = DBL_MAX;
    int limit = INT_MAX;
    const char *output_dir = ".";
    int create_jpeg = 0;
    int create_index = 0;

    int opt;
    while ((opt = getopt(argc, argv, "f:s:e:l:d:ji")) != -1) {
        switch (opt) {
            case 'f':
                filename = optarg;
                break;
            case 's':
                start_position = atof(optarg);
                break;
            case 'e':
                end_position = atof(optarg);
                break;
            case 'l':
                limit = atoi(optarg);
                break;
            case 'd':
                output_dir = optarg;
                break;
            case 'j':
                create_jpeg = 1;
                break;
            case 'i':
                create_index = 1;
                break;
            default:
                print_usage(argv[0]);
                exit(EXIT_FAILURE);
        }
    }

    if (!filename) {
        print_usage(argv[0]);
        exit(EXIT_FAILURE);
    }

    avformat_network_init(); // Initialize libavformat and network components

    AVFormatContext *fmt_ctx = NULL;
    AVCodecContext *video_dec_ctx = NULL;
    AVStream *video_stream = NULL;
    int video_stream_idx = -1;

    open_input_file(filename, &fmt_ctx);
    find_video_stream(fmt_ctx, &video_stream_idx, &video_dec_ctx, &video_stream);
    process_keyframes(fmt_ctx, video_dec_ctx, video_stream, start_position, end_position, limit, output_dir, create_jpeg, create_index);
    cleanup(fmt_ctx, video_dec_ctx);

    return 0;
}

// Function Implementations
void fatal(const char *message, int errnum) {
    char errbuf[128];
    av_strerror(errnum, errbuf, sizeof(errbuf));
    fprintf(stderr, "%s: %s\n", message, errbuf);
    exit(EXIT_FAILURE);
}

void process_frame(AVCodecContext *video_dec_ctx, AVFrame *frame, int *frame_count, double packet_dts_time, const char *output_dir, int create_jpeg, int create_index, FILE *json_file, int *first_entry) {
    if (frame->key_frame == 1 && frame->pict_type == AV_PICTURE_TYPE_I) {
        if (create_jpeg) {
            save_frame_as_jpeg(frame, video_dec_ctx->width, video_dec_ctx->height, *frame_count, output_dir);
        }

        if (create_index) {
            if (!*first_entry) {
                fprintf(json_file, ",\n");
            }
            fprintf(json_file, "    \"%.3f\": \"%s/frame-%04d.jpg\"", packet_dts_time, output_dir, *frame_count);
            *first_entry = 0;
        }

        (*frame_count)++;
    }
}

void open_input_file(const char *filename, AVFormatContext **fmt_ctx) {
    int ret = 0;
    if ((ret = avformat_open_input(fmt_ctx, filename, NULL, NULL)) < 0) {
        fatal("Could not open source file", ret);
    }

    if (avformat_find_stream_info(*fmt_ctx, NULL) < 0) {
        fatal("Could not find stream information", ret);
    }
}

void find_video_stream(AVFormatContext *fmt_ctx, int *video_stream_idx, AVCodecContext **video_dec_ctx, AVStream **video_stream) {
    const AVCodec *dec = NULL;
    int ret = av_find_best_stream(fmt_ctx, AVMEDIA_TYPE_VIDEO, -1, -1, &dec, 0);
    if (ret < 0) {
        fatal("Could not find video stream in input file", ret);
    }
    *video_stream_idx = ret;
    *video_stream = fmt_ctx->streams[*video_stream_idx];

    *video_dec_ctx = avcodec_alloc_context3(dec);
    if (!*video_dec_ctx) {
        fatal("Failed to allocate the video codec context", AVERROR(ENOMEM));
    }

    ret = avcodec_parameters_to_context(*video_dec_ctx, (*video_stream)->codecpar);
    if (ret < 0) {
        fatal("Failed to copy codec parameters to context", ret);
    }

    if (avcodec_open2(*video_dec_ctx, dec, NULL) < 0) {
        fatal("Failed to open codec for stream", ret);
    }
}

void process_keyframes(AVFormatContext *fmt_ctx, AVCodecContext *video_dec_ctx, AVStream *video_stream, double start_position, double end_position, int limit, const char *output_dir, int create_jpeg, int create_index) {
    AVPacket packet;
    AVFrame *frame = av_frame_alloc();
    if (!frame) {
        fatal("Could not allocate frame", AVERROR(ENOMEM));
    }

    int frame_count = 0;
    double packet_dts_time;
    int ret = 0;

    FILE *json_file = NULL;
    if (create_index) {
        char json_filename[1024];
        snprintf(json_filename, sizeof(json_filename), "%s/index.json", output_dir);
        json_file = fopen(json_filename, "w");
        if (!json_file) {
            fatal("Could not open index.json for writing", AVERROR(errno));
        }
        fprintf(json_file, "{\n");
    }

    int first_entry = 1;  // Flag to handle comma placement

    while (av_read_frame(fmt_ctx, &packet) >= 0) {
        if (packet.stream_index == video_stream->index) {
            packet_dts_time = packet.dts * av_q2d(video_stream->time_base);

            if (packet_dts_time >= end_position) {
                av_packet_unref(&packet);
                break;
            }

            if (packet_dts_time >= start_position) {
                ret = avcodec_send_packet(video_dec_ctx, &packet);
                if (ret < 0) {
                    fatal("Error sending packet for decoding", ret);
                    av_packet_unref(&packet);
                    continue;
                }

                while ((ret = avcodec_receive_frame(video_dec_ctx, frame)) >= 0) {
                    process_frame(video_dec_ctx, frame, &frame_count, packet_dts_time, output_dir, create_jpeg, create_index, json_file, &first_entry);

                    if (frame_count >= limit) {
                        av_packet_unref(&packet);
                        goto end;
                    }
                }

                if (ret != AVERROR(EAGAIN) && ret != AVERROR_EOF) {
                    fatal("Error during decoding", ret);
                    av_packet_unref(&packet);
                    exit(EXIT_FAILURE);
                }
            }
        }
        av_packet_unref(&packet);
    }

end:
    if (json_file) {
        fprintf(json_file, "\n}\n");
        fclose(json_file);
    }

    av_frame_free(&frame);
}

void save_frame_as_jpeg(AVFrame *frame, int width, int height, int frame_index, const char *output_dir) {
    AVCodecContext *jpeg_ctx = NULL;
    const AVCodec *jpeg_codec = NULL;
    AVPacket *packet = NULL;
    int ret;

    jpeg_codec = avcodec_find_encoder(AV_CODEC_ID_MJPEG);
    if (!jpeg_codec) {
        fatal("Codec not found", AVERROR_ENCODER_NOT_FOUND);
    }

    jpeg_ctx = avcodec_alloc_context3(jpeg_codec);
    if (!jpeg_ctx) {
        fatal("Could not allocate video codec context", AVERROR(ENOMEM));
    }

    jpeg_ctx->pix_fmt = AV_PIX_FMT_YUVJ420P;
    jpeg_ctx->height = height;
    jpeg_ctx->width = width;
    jpeg_ctx->time_base = (AVRational){1, 1}; // Set to 1/1 for simplicity

    ret = avcodec_open2(jpeg_ctx, jpeg_codec, NULL);
    if (ret < 0) {
        fatal("Could not open codec", ret);
    }

    // Convert frame to YUVJ420P format
    AVFrame *yuv_frame = av_frame_alloc();
    if (!yuv_frame) {
        fatal("Could not allocate YUV frame", AVERROR(ENOMEM));
    }

    yuv_frame->format = AV_PIX_FMT_YUVJ420P;
    yuv_frame->width = width;
    yuv_frame->height = height;
    ret = av_frame_get_buffer(yuv_frame, 32);
    if (ret < 0) {
        fatal("Could not allocate YUV frame buffer", ret);
    }

    ret = av_frame_make_writable(yuv_frame);
    if (ret < 0) {
        fatal("Could not make YUV frame writable", ret);
    }

    av_image_copy(yuv_frame->data, yuv_frame->linesize, (const uint8_t **)(frame->data), frame->linesize, (enum AVPixelFormat)frame->format, width, height);

    // Allocate packet
    packet = av_packet_alloc();
    if (!packet) {
        fatal("Could not allocate packet", AVERROR(ENOMEM));
    }

    ret = avcodec_send_frame(jpeg_ctx, yuv_frame);
    if (ret < 0) {
        fatal("Error sending a frame for encoding", ret);
    }

    ret = avcodec_receive_packet(jpeg_ctx, packet);
    if (ret < 0) {
        fatal("Error during encoding", ret);
    }

    char filename[1024];
    snprintf(filename, sizeof(filename), "%s/frame-%04d.jpg", output_dir, frame_index);

    FILE *jpeg_file = fopen(filename, "wb");
    if (!jpeg_file) {
        fatal("Could not open jpeg file for writing", AVERROR(errno));
    }
    fwrite(packet->data, 1, packet->size, jpeg_file);
    fclose(jpeg_file);

    av_packet_free(&packet);
    avcodec_free_context(&jpeg_ctx);
    av_frame_free(&yuv_frame);
}

void cleanup(AVFormatContext *fmt_ctx, AVCodecContext *video_dec_ctx) {
    avcodec_free_context(&video_dec_ctx);
    avformat_close_input(&fmt_ctx);
}
