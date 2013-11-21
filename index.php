<?php

error_reporting(E_ALL);
ini_set('display_errors', true);
ini_set('xdebug.var_display_max_children', -1);
require 'algs.php';

class I{
    /** @var \Imagick изображение */
    private $_i;
    /** @var int ширина */
    private $_w;
    /** @var int высота */
    private $_h;
    /** @var  array Бинарная матрица, упакованная в int'ы */
    private $_mPacked;

    /** Коэффициенты границ яркости для строк */
    const k1 = .05;
    const k2 = .05;
    /** Коэффициенты границ яркости для слов */
    const k3 = .1;
    const k4 = .1;
    /** Коэффициент границ яркости для символов */
    const k5 = .1;

    /** Коэффициент отношения ширины символа к высоте для шрифта */
    const FONT_KOEF = 0.6;
    const k6 = 0.15;

    const TOP_BLUR = 4;
//    const TOP_BLUR = 0;
    const BOTTOM_BLUR = 2;
//    const BOTTOM_BLUR = 0;
    const LEFT_BLUR = 1;
    const RIGHT_BLUR = 1;

    /**
     * @param $path
     */
    public function __construct($path){
        $begin = new Imagick($path);
        $this->_w = 1392;
        $this->_h = $begin->getimageheight();

        $this->_i = $im = $begin->getimageregion($this->_w, $this->_h, 0 ,0);
//        $this->_mPacked = $this->_imageToMatrix($im, true);
    }

    /**
     * Полный анализ изображения
     */
    public function analyze(){
        //выделяем строки
        $edges = $this->getEdges();
//        $this->drawEdges($edges); die;
        $lines = $this->getLines($edges); /** @var Imagick[] $lines */

        //выделяем слова
        $line2words = [];
        $wordEdges = [];

        $fromLine = 0;
        $toLine = 10; //count($lines);
        set_time_limit(300);

        for($i = $fromLine; $i < $toLine; ++$i){
            $wordEdges[$i] = $this->getWordEdges($lines[$i], $edges[$i]);
            $words = $this->getWords($wordEdges[$i], $lines[$i]);
            $line2words[$i] = $words;
        }

//        $stacked = new Imagick();
        for($j = $fromLine; $j < $toLine; ++$j){
            for($i = 0; $i < count($line2words[$j]); ++$i){
                $word = $line2words[$j][$i];

                $letterEdges = $this->getLetterEdges($word);
//                $shifted = array_map(function($v) use($wordEdges, $i, $j) {
//                    return $v + $wordEdges[$j][$i]['left'];
//                }, $letterEdges);
//                $this->drawVerticalEdges($shifted, $lines[$j], false, true);
                $this->_saveLetters($word, $letterEdges);
            }
//            $stacked->addimage($lines[$j]);
        }
        echo 'ok';
        
//        $stacked->resetiterator();
//        $result = $stacked->appendimages(true);
//        $this->render($result);
//        $this->render();
//        $this->drawEdges($edges, $result);
    }

    private $_fileN = 0;
    private function _saveLetters(Imagick $word, $edges){
        $word->setimagepage(0,0,0,0);
        $h = $word->getimageheight();

        $first = false;
        $start = $edges[0];
        foreach($edges as $edge){
            if(!$first){
                $first = true;
                continue;
            }
            $letter = $word->getimageregion($edge - $start, $h, $start, 0);
            $letter->setImageFormat('png');
            ++$this->_fileN;
            $letter->writeimage('letters/' . $this->_fileN . '.jpg');
            $start = $edge;
        }
    }

    public function checkLetters(){
        $base = [];
        foreach(scandir('base') as $img){
            if($img == '.' || $img == '..'){
                continue;
            }

            $im = new Imagick('base/' . $img);
            $base[] = $im; //$this->_imageToMatrix($im);
        }

        $result = [];

        $baseH = $base[0]->getimageheight();
        $baseW = $base[0]->getimagewidth();
        foreach(scandir('letters') as $img){
            if($img == '.' || $img == '..'){
                continue;
            }

            $im = new Imagick('letters/' . $img);
            $letter = $this->_imageToMatrix($im);

//            $result[$img] = $this->_compareLetters($base[0], $letter);
            $h = max($baseH, $im->getimageheight());
            $w = max($baseW, $im->getimagewidth());

            $im->scaleimage($w, $h);
            $baseIm = clone $base[0];
            $baseIm->scaleimage($w, $h);

            $result[$img] = $im->compareimages($baseIm, Imagick::METRIC_MEANSQUAREERROR)[1];
        }
        arsort($result);
        var_dump($result);
    }

    private function _compareLetters($one, $two){
        $h1 = count($one);
        $w1 = count($one[0]);
        $h2 = count($two);
        $w2 = count($two[0]);

        $h = max($h1, $h2);
        $w = max($w1, $w2);

        $kh = min($h1, $h2) / $h;
        $kw = min($w1, $w2) / $w;

        $sum = 0;
        for($i = 0; $i < $h; ++$i){
            for($j = 0; $j < $w; ++$j){
                $x1 = $j;
                $x2 = (int)floor($kw * $j);
                if($w1 < $w2){
                    $x = $x1;
                    $x1 = $x2;
                    $x2 = $x;
                }

                $y1 = $i;
                $y2 = (int)floor($kh * $i);
                if($h1 < $h2){
                    $y = $y1;
                    $y1 = $y2;
                    $y2 = $y;
                }

                if(!isset($one[$y1][$x1]) || !isset($two[$y2][$x2])){
                    var_dump($w1, $h1, $w2, $h2, $x1, $y1, $x2, $y2, $kw, $kh); die;
                }
                if($one[$y1][$x1] == $two[$y2][$x2]){
                    ++$sum;
                }
            }
        }

        return round($sum / $w / $h * 100);
    }

    /**
     * Рассчёт границ символов
     * @param Imagick $word
     * @return array
     */
    public function getLetterEdges(Imagick $word){
        $m = $this->_imageToMatrix($word);
        $edges = Alg::getLetterEdges($m, self::FONT_KOEF, self::k5, self::k6);
        return $edges;
    }

    /**
     * Разбить картинку строки на картинки слов
     * @param array $wordEdges
     * @param Imagick $line
     * @return Imagick[]
     */
    public function getWords($wordEdges, Imagick $line){
        $result = [];
        $line->setImagePage(0, 0, 0, 0); //сбрасываем метрику для изображений
        foreach($wordEdges as $one){
            $result[] = $line->getimageregion($one['right'] - $one['left'], $line->getimageheight(), $one['left'], 0);
        }
        return $result;
    }

    /**
     * Выделение слов из строки
     * @param Imagick $line
     * @return array[]   col => left|right
     */
    public function getWordEdges(Imagick $line){
        //делаем контуры букв жирными
        $copy = clone $line;
        $copy->gaussianblurimage(10, 5);
        $max = $copy->getQuantumRange();
        $max = $max['quantumRangeLong'];
        $copy->thresholdImage(0.77 * $max);

        $m = $this->_imageToMatrix($copy);
        unset($copy);

        $edges = Alg::getWordEdges($m, self::k3, self::k4);
        foreach($edges as &$edge){
            $edge['left'] -= self::LEFT_BLUR;
            $edge['right'] += self::RIGHT_BLUR;
        }
        return $edges;
    }

    /**
     * Переводит изображение в матрицу из 0 и 1 и упаковывает в int'ы
     * @param Imagick $im изображение
     * @param bool $packed
     * @return array
     */
    private function _imageToMatrix(Imagick $im, $packed = false){
        $m = [];

        $it = $im->getpixeliterator();
        while($row = $it->getnextiteratorrow()){ /** @var ImagickPixel[] $row */
            $s = [];
            foreach($row as $pixel){
                $s[] = $pixel->getcolor()['r'] / 255;
            }

            if($packed){
                //режем строку на куски по 32 цифры и упаковываем в int'ы
                $m[] = array_map('bindec', str_split(implode($s), 32));
            }else{
                $m[] = $s;
            }
        }

        return $m;
    }

    /**
     * Возвращает часть матрицы и распаковывает её
     * @param array $m
     * @param int $fromRow
     * @param int $toRow
     * @param int $width
     * @return array
     */
    private function _getMatrix($m, $fromRow, $toRow, $width = null){
        $result = [];
        $lengths = [];
        for($i = $fromRow; $i < $toRow; ++$i){
            $s = implode(array_map('decbin', $m[$i]));
            $lengths[] = strlen($s);
        }
        $maxLen = $width ? : max($lengths);
        foreach($result as &$row){
            $c = $maxLen - strlen($row);
            $row = str_split(str_repeat('0', $c) . $row);
        }
        return $result;
    }

    /**
     * Получить границы строк
     * @return array Массив границ
     */
    public function getEdges(){
        $s = $this->_getS();
        $s = array_map(function($v){
            return 255 - $v;
        }, $s);

        $lines = Alg::getLines($s, self::k1, self::k2);

        foreach($lines as &$line){
            $line['top'] -= self::TOP_BLUR;
            $line['bottom'] += self::BOTTOM_BLUR;
        }
        return $lines;
    }

    /**
     * Порезать исходное изображение на изображения строк
     * @param array $edges
     * @return Imagick[]
     */
    public function getLines($edges){
        $im = $this->_i;
        $result = [];
        foreach($edges as $one){
            $result[] = $im->getimageregion($this->_w, $one['bottom'] - $one['top'], 0, $one['top']);
        }
        return $result;
    }

    /**
     * Рисование картинки с нанесёнными на неё границами строк
     * @param $edges
     * @param null $im
     */
    public function drawEdges($edges, $im = null){
        $draw = new ImagickDraw();

        $draw->setstrokecolor(new ImagickPixel('green'));
        foreach($edges as $one){
            $level = $one['top'];
            $draw->line(0, $level, $this->_w, $level);
        }

        $draw->setstrokecolor(new ImagickPixel('red'));
        foreach($edges as $one){
            $level = $one['bottom'];
            $draw->line(0, $level, $this->_w, $level);
        }

        $im = $im ? : $this->_i;
        $im->drawimage($draw);
        $this->render($im);
    }

    /**
     * Рисование картинки с нанесёнными на неё вертикальными границами слов/букв
     * @param array $edges
     * @param Imagick $im
     * @param bool $output
     * @param bool $oneColor
     */
    public function drawVerticalEdges($edges, $im, $output = true, $oneColor = false){
        $draw = new ImagickDraw();

        $height = $im->getimageheight();
        $draw->setstrokecolor(new ImagickPixel('green'));
        if($oneColor){
            foreach($edges as $one){
                $draw->line($one, 0, $one, $height);
            }
        }else{
            foreach($edges as $one){
                $level = $one['left'];
                $draw->line($level, 0, $level, $height);
            }

            $draw->setstrokecolor(new ImagickPixel('red'));
            foreach($edges as $one){
                $level = $one['right'];
                $draw->line($level, 0, $level, $height);
            }
        }


        $im->drawimage($draw);
        if($output){
            $this->render($im);
        }
    }

    public function countS(){
        $im = $this->_i;
        $it = $im->getpixeliterator();

        $a = [];
        $t = microtime(true);
        $i = 0;
        set_time_limit(100);
        while($row = $it->getnextiteratorrow()){ /** @var ImagickPixel[] $row */
            $s = [];
            foreach($row as $pixel){
                $s[] = $pixel->getcolor()['r'];
            }
            $a[] = array_sum($s) / count($s);
        }

        $this->_saveS($a);

        ini_set('xdebug.var_display_max_data', -1);
        ini_set('xdebug.var_display_max_children', -1);
        var_dump($a);
    }

    private function _saveS($a){
        $f = fopen('s.txt', 'w');
        fwrite($f, json_encode($a));
        fclose($f);
    }

    private function _getS(){
        $s = file_get_contents('s.txt');
        return json_decode($s, true);
    }

    public function checkPixels(){
        $im = $this->_i;
        $it = $im->getpixeliterator();

        $t = microtime(true);
        $i = 0;
        $a = [];

        set_time_limit(100);
        while($row = $it->getnextiteratorrow()){
            foreach($row as $pixel){ /** @var ImagickPixel $pixel */
                $color = $pixel->getcolorasstring();
//                if(array_diff($color, [0, 1, 255])){
//                if(!in_array($color, ['srgb(255,255,255)', 'srgb(0%,0%,0%)'])){
//                    var_dump($color);
//                    die;
//                }
                $a[] = $color;
            }
            $a = array_unique($a);
        }
        printf('Time: %s I: %s', microtime(true) - $t, $i);
        var_dump($a);
    }

    public function render($im = null){
        $im = $im ? : $this->_i;
        $im->setImageFormat('png');
        header('Content-Type: image/png');
        echo $im;
    }
}

$i = new I('p0011-sel.png');
//$i->analyze();
$i->checkLetters();