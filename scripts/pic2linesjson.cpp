#include "opencv2/highgui/highgui.hpp"
#include <stdio.h>
#include "opencv2/imgproc/imgproc.hpp"

int main(int argc, char** argv)
{
    int threshold = 80;
    double minLineLength = 400;
    double maxLineGap = 10;
    if (argc > 2) {
        threshold = atoi(argv[2]);
    }
    if (argc > 3) {
        minLineLength = atof(argv[3]);
    }
    if (argc > 4) {
        maxLineGap = atof(argv[4]);
    }


    cv::Mat src1;
    src1 = cv::imread(argv[1], CV_LOAD_IMAGE_COLOR);

    cv::Mat img, output;
    cvtColor(src1, img, CV_BGR2GRAY);
    output = src1;
    cv::Size size(3, 3);
    cv::GaussianBlur(img, img, size, 0);
    adaptiveThreshold(img, img, 255, CV_ADAPTIVE_THRESH_MEAN_C, CV_THRESH_BINARY, 75, 10);
    cv::bitwise_not(img, img);

    cv::vector<cv::Vec4i> lines;

    HoughLinesP(img, lines, 1, CV_PI / 180, threshold, minLineLength, maxLineGap);
    cv::Size img_size;
    printf("{\"size\":[%d,%d],\"lines\":", img.size().width, img.size().height);

    printf("[");
    int start = 1;
    for( size_t i = 0; i < lines.size(); i++ ) {
        cv::Vec4i l = lines[i];
        if (start) {
            start = 0;
        } else {
            printf(",");
        }
        cv::line(output, cv::Point(l[0], l[1]), cv::Point(l[2], l[3]), cv::Scalar(0, 0, 255), 1);
        printf("[%d,%d,%d,%d]", l[0], l[1], l[2], l[3]);
    }
    cv::imwrite("opencv.png", output);
    printf("]}\n");

    return 0;
}
