input="origin.png" #要被處理的檔案
mask="../mask.png" #遮罩檔
resolution=`identify "$input" |cut -d' ' -f3`

# 表格最上面那條線
x1=351
y1=751
x2=6770
y2=775

degree=`echo "a(($y2-$y1)/($x2-$x1))*45/a(1)"|bc -l`

convert "$mask" -extent $resolution -filter point -distort SRT "0,0 1 $degree $x1,$y1" -negate region.png #製作遮罩
convert "$input" -alpha on \( +clone -channel a -fx 0 \) +swap region.png -composite focus.png #取出該被特別關照的區塊
convert focus.png -morphology Close Octagon:2 denoised.png #去除汙點 (浮水印)
convert "$input" denoised.png -composite result.png #疊回去
