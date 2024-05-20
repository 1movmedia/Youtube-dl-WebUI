#include <libavformat/avformat.h>
#include <libavcodec/avcodec.h>
#include <libavutil/timestamp.h>
#include <libavutil/opt.h>
#include <stdio.h>
#include <stdlib.h>
#include <float.h>

void open_input_file(const char *filename, AVFormatContext **fmt_ctx);
void find_video_stream(AVFormatContext *fmt_ctx, int *video_stream_idx, AVCodecContext **video_dec_ctx, AVStream **video_stream);
void process_frames(AVFormatContext *fmt_ctx, AVCodecContext *video_dec_ctx, AVStream *video_stream, double start_position, double end_position, int limit);
void cleanup(AVFormatContext *fmt_ctx, AVCodecContext *video_dec_ctx);

void find_keyframes(const char *filename, double start_position, double end_position, int limit) {
    AVFormatContext *fmt_ctx = NULL;
    AVCodecContext *video_dec_ctx = NULL;
    AVStream *video_stream = NULL;
    int video_stream_idx = -1;

    avformat_network_init(); // Initialize libavformat and network components
    open_input_file(filename, &fmt_ctx);
    find_video_stream(fmt_ctx, &video_stream_idx, &video_dec_ctx, &video_stream);
    process_frames(fmt_ctx, video_dec_ctx, video_stream, start_position, end_position, limit);
    cleanup(fmt_ctx, video_dec_ctx);
}

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

    avcodec_parameters_to_context(*video_dec_ctx, (*video_stream)->codecpar);

    if ((ret = avcodec_open2(*video_dec_ctx, dec, NULL)) < 0) {
        fprintf(stderr, "Failed to open codec for stream #%u\n", *video_stream_idx);
        exit(EXIT_FAILURE);
    }
}

void process_frames(AVFormatContext *fmt_ctx, AVCodecContext *video_dec_ctx, AVStream *video_stream, double start_position, double end_position, int limit) {
    AVPacket pkt;
    av_init_packet(&pkt);
    pkt.data = NULL;
    pkt.size = 0;

    int frame_count = 0;
    double pkt_dts_time;

    while (av_read_frame(fmt_ctx, &pkt) >= 0) {
        if (pkt.stream_index == video_stream->index) {
            pkt_dts_time = pkt.dts * av_q2d(video_stream->time_base);
            if (pkt_dts_time >= start_position && pkt_dts_time <= end_position) {
                if (pkt.flags & AV_PKT_FLAG_KEY) {
                    printf("Keyframe at %f seconds\n", pkt_dts_time);
                    frame_count++;
                    if (frame_count >= limit) {
                        break;
                    }
                }
            }
        }
        av_packet_unref(&pkt);
    }
}

void cleanup(AVFormatContext *fmt_ctx, AVCodecContext *video_dec_ctx) {
    avcodec_free_context(&video_dec_ctx);
    avformat_close_input(&fmt_ctx);
}

int main(int argc, char **argv) {
    if (argc < 2) {
        fprintf(stderr, "Usage: %s <video file> [start position] [end position] [limit]\n", argv[0]);
        exit(EXIT_FAILURE);
    }

    const char *filename = argv[1];
    double start_position = (argc > 2) ? atof(argv[2]) : 0;
    double end_position = (argc > 3) ? atof(argv[3]) : DBL_MAX;
    int limit = (argc > 4) ? atoi(argv[4]) : INT_MAX;

    find_keyframes(filename, start_position, end_position, limit);

    return 0;
}
