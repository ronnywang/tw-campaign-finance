#include "opencv2/highgui/highgui.hpp"
#include <stdio.h>
#include "opencv2/imgproc/imgproc.hpp"

int main(int argc, char** argv)
{
    cv::Mat src1;
    src1 = cv::imread(argv[1], CV_LOAD_IMAGE_COLOR);

    cv::Mat img;
    cvtColor(src1, img, CV_BGR2GRAY);
    cv::Size size(3, 3);
    cv::GaussianBlur(img, img, size, 0);
    adaptiveThreshold(img, img, 255, CV_ADAPTIVE_THRESH_MEAN_C, CV_THRESH_BINARY, 75, 10);
    cv::bitwise_not(img, img);

    cv::vector<cv::Vec4i> lines;

    HoughLinesP(img, lines, 1, CV_PI / 180, 80, 400, 10);
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
        printf("[%d,%d,%d,%d]", l[0], l[1], l[2], l[3]);
    }
    printf("]}\n");

    return 0;
}
