<?php
# +----------------------------------------------------------------------
# | 验证码类
# | 修改自 https://github.com/top-think/think-captcha
# +----------------------------------------------------------------------

namespace Aknife;

class Captcha
{
    // 验证码字符集合
    private static $codeSet = '0123456789abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNPOQRSTUVWXYZ';
    // 小学常用100汉字
    private static $codeSetZh = '的一国在人了有中是年和大业不为发会工经上地市要个产这出行作生家以成到日民来我部对进多全建他公开们场展时理新方主企资实学报制政济用同于法高长现本月定化加动合品重关机分力自外者区能设后就等体下万元社过前面';

    // 验证码图片实例
    private static $image = null;
   
    // 验证码图片宽度
    private static $width = 0;
    // 验证码图片高度
    private static $height = 0;
    // 验证码类型 [1-字母数字, 2-算术, 3-中文]
    private static $type = 1;
    // 验证码位数
    private static $length = 5;
    // 验证码字体大小
    private static $fontSize = 25;
    // 使用动图 [0-不启用,其他大于0的数字为gif帧速度]
    private static $gif = 30;
    // 区分大小写
    private static $uppercase = 0;

    // 保存验证码的 session 前缀
    private static $session = 'php_compser_aknife_captcha';

    /**
     * 验证验证码
     * @param string $code 要验证的验证码值
     * @param string $session
     * @return string
     */
    public static function check($code,$session='')
    {
        // 判断sessino key
        $session_key = $session ?: self::$session;
        $session_code = $_SESSION[$session_key]['captcha'];
        if( $code != $session_code ){
            return false;
        }else{
            unset($_SESSION[$session]['captcha']);
            return true;
        }
    }

    /**
     * 输出验证码图像
     * @param array $config 配置文件
     * @param string $type 返回类型[blob，array]
     */
    public static function create($config)
    {
        // 解析配置参数
        foreach( $config as $key=>$val ){
            if (property_exists(self::class, $key)) {
                self::${$key} = $val;
            }
        }

        // 生成验证码内容
        $generator = self::generate();

        // 生成GIF
        if( self::$gif > 0 && extension_loaded('imagick') ){
            $gifImagek = new \Imagick(); //建立一个对象。
            $gifImagek->setFormat( "gif" ); //设置它的类型。

            for( $i=0;$i<10;$i++){
                $frame = new \Imagick();
                $frame->readImageBlob(self::createImage($generator));
                $frame->setImageDelay(self::$gif);

                $gifImagek->addImage($frame);
            }

            $content  = $gifImagek->getImagesBlob();
            $gifImagek->destroy();
        }else{
            self::$gif = 0;
            $content = self::createImage($generator);
        }
       
        self::$gif == 1 ? header("content-type:image/gif;charset=utf-8") : header("content-type:image/png;charset=utf-8");
        echo $content;
        exit;
    }

    /**
     * 创建验证码图像
     * @param array  $generator
     * @return blob 
     */
    private static function createImage($generator)
    {
        // 图片宽(px)
        self::$width || self::$width = self::$length * self::$fontSize * 1.5 + self::$length * self::$fontSize / 2;

        // 图片高(px)
        self::$height || self::$height = self::$fontSize * 2.5;

        self::$width = intval(self::$width);
        self::$height = intval(self::$height);

        // 建立一幅 $width x $height 的图像
        self::$image = imagecreate(self::$width,self::$height);
        // 设置背景
        imagecolorallocate(self::$image, rand(200,255), rand(200,255), rand(200,255));

        // 验证码字体随机颜色
        $textColor = imagecolorallocate(self::$image, mt_rand(1, 150), mt_rand(1, 150), mt_rand(1, 150));

        // 绘制杂点
        self::writeNoise();

        // 绘制干扰线
        self::writeCurve();

        // 绘验证码
        $text = self::$type == 3 ? self::mb_str_split($generator) : str_split($generator);

        // 验证码使用随机字体
        $fontttf = dirname(__DIR__) . '/src/font.ttf';
        foreach ($text as $index => $char) {

            // 字体角度
            $angle = self::$type == 2 ? 0 : mt_rand(-15, 15);

            // 计算坐标轴
            $indexLength = self::$type == 2 ? $index+0.5 : (self::$type == 3 ? $index-0.2 : $index);
            $x = (self::$width/count($text))*$indexLength + (self::$width/count($text))/2 - self::$fontSize/2;
            $y = self::$height/2+self::$fontSize*0.6;

            imagettftext(self::$image, intval(self::$fontSize), $angle, intval($x), intval($y), $textColor, $fontttf, $char);
        }

        ob_start();
        // 输出图像
        imagepng(self::$image);
        $content = ob_get_clean();
        imagedestroy(self::$image);

        return $content;
    }

    /**
     * 创建验证码，并写入session
     * @return array 返回创建的验证码key和值
     */
    private static function generate()
    {
        $string = '';

        // 如果是算术组合
        if( self::$type == 2 ){
            // 获取运算符
            $operateArr = ['+','-','*','/'];
            $operateKey = array_rand($operateArr);

            $x   = random_int(1, 50);
            $y   = random_int(1, 50);

            if( $operateArr[$operateKey] == '+' ){
                $string = $x.' + '.$y.' = ';
                $key = intval($x + $y);
            }elseif( $operateArr[$operateKey] == '-' ){
                $string = max($x,$y).' - '.min($x,$y).' = ';
                $key =  intval(abs($x - $y));
            }elseif( $operateArr[$operateKey] == '*' ){
                $x   = random_int(1, 9);
                $y   = random_int(1, 9);

                $string = $x.' x '.$y.' = ';
                $key =  intval($x * $y);
            }else{
                $y   = random_int(1,11);
                $x = $y*random_int(2,9);
                $string = max($x,$y).' / '.min($x,$y).' = ';
                $key =  intval($x * $y);
            }

            $key .= '';
        }else{
            $characters = self::$type == 3 ? self::mb_str_split(self::$codeSetZh) : str_split(self::$codeSet);
            for ($i = 0; $i < self::$length; $i++) {
                $string .= $characters[rand(0, count($characters) - 1)];
            }

            // 判断是否转换小写
            $key = self::$uppercase ? $string : mb_strtolower($string, 'UTF-8');
        }

        // 写入session
        $_SESSION[self::$session]['captcha'] = $key;

        return $string;
    }

    /**
     * 干扰线
     */
    private static function writeCurve()
    {
        $interfere_line = 15;
        for ($i = 0; $i < $interfere_line; $i++) {
            $x1 = rand(1, self::$width - 1);
            $y1 = rand(1, self::$height - 1);
            $x2 = rand(1, self::$width - 1);
            $y2 = rand(1, self::$height - 1);
            $noiseColor = imagecolorallocate(self::$image, mt_rand(100,255), mt_rand(100,255), mt_rand(100,255));

            imageline(self::$image, $x1, $y1, $x2, $y2, $noiseColor);
        }
    }

    /**
     * 画杂点
     * 往图片上写不同颜色的字母或数字
     */
    private static function writeNoise()
    {
        for ($i = 0; $i < 10; $i++) {
            //杂点颜色
            $noiseColor = imagecolorallocate(self::$image, mt_rand(100,255), mt_rand(100,255), mt_rand(100,255));
            for ($j = 0; $j < 5; $j++) {
                // 绘杂点
                $codeSet = self::$codeSet[rand(0, strlen(self::$codeSet)-1)];
                imagestring(self::$image, rand(1,10), mt_rand(0, self::$width), mt_rand(0, self::$height), $codeSet, $noiseColor);
            }
        }
    }

    /**
    * 将字符串分割为数组
    * @param  string $str 字符串
    * @return array       分割得到的数组
    */
    private static function mb_str_split($str){
       return preg_split('/(?<!^)(?!$)/u', $str );
   }
}
