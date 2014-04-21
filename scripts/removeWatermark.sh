input="origin.png" #要被處理的檔案
output="result.png"
mask="../mask.png" #遮罩檔
resolution=`identify "$input" |cut -d' ' -f3`

# 表格最上面那條線
x1=351
y1=751
x2=6770
y2=775

degree=`echo "a(($y2-$y1)/($x2-$x1))*45/a(1)"|bc -l`

convert "$input" \( \
    +clone \
    \( "$mask" -extent $resolution -filter point -distort SRT "0,0 1 $degree $x1,$y1" -negate \) \
    -compose CopyOpacity \
    -composite \
    -morphology Close Octagon:2 \
\) -compose Over -composite -monochrome "$output"
