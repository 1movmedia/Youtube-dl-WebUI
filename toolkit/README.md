# Toolkit

This toolkit provides utilities for processing video files, including extracting keyframes and saving them as JPEG images.

## Directory Structure

- `keyframes.c`: The main C program for extracting keyframes from video files.
- `.vscode/`: Contains VSCode configuration files for building and debugging the project.
  - `tasks.json`: Defines the build tasks.
  - `launch.json`: Defines the debug configurations.
  - `extensions.json`: Recommends VSCode extensions for the project.
- `Makefile`: The makefile for building the project.
- `.gitignore`: Specifies files and directories to be ignored by git.

## Prerequisites

- GCC (GNU Compiler Collection)
- FFmpeg libraries (`libavformat`, `libavcodec`, `libavutil`)
- VSCode (Visual Studio Code) with recommended extensions

## Building the Project

To build the project, run the following command in the terminal:

```sh
make all
```

This will compile the `keyframes.c` file and generate the `keyframes` executable.

## Running the Program

To run the program, use the following command:

```sh
./keyframes -f <video file> [-s start position] [-e end position] [-l limit] [-d output directory] [-j (create jpegs)] [-i (create index json)]
```

### Example

```sh
./keyframes -f ../tmp/test_video_0.mp4 -s 0 -e 10 -l 100 -d ../tmp -j -i
```

This command will extract keyframes from `test_video_0.mp4` between 0 and 10 seconds, with a limit of 100 keyframes, and save them as JPEG images in the `../tmp` directory. It will also create an `index.json` file in the same directory.

## Debugging with VSCode

To debug the program using VSCode:

1. Open the project in VSCode.
2. Press `F5` to start debugging with the default configuration (`Debug keyframes`).

## Cleaning Up

To clean up the build files, run the following command:

```sh
make clean
```

This will remove the object files and the `keyframes` executable.

## License

This project is licensed under the MIT License.
