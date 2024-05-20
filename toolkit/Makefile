CC = gcc
CFLAGS = -Wall -g
LIBS = -lavformat -lavcodec -lavutil -lswscale

SRCS = keyframes.c
OBJS = $(SRCS:.c=.o)
TARGET = keyframes

.PHONY: all clean

all: $(TARGET)

$(TARGET): $(OBJS)
	$(CC) $(CFLAGS) -o $(TARGET) $(OBJS) $(LIBS)

%.o: %.c
	$(CC) $(CFLAGS) -c $< -o $@

clean:
	rm -f $(OBJS) $(TARGET)
