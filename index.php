<?php

error_reporting(E_ALL);
ini_set('display_errors', true);
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
    const k1 = .1;
    const k2 = .1;
    /** Коэффициенты границ яркости для слов */
    const k3 = .1;
    const k4 = .1;
    /** Коэффициент границ яркости для символов */
    const k5 = .3;

    /** Коэффициент отношения ширины символа к высоте для шрифта */
    const FONT_KOEF = 0.5;

    const TOP_BLUR = 10;
    const BOTTOM_BLUR = 6;
    const LEFT_BLUR = 0;
    const RIGHT_BLUR = 0;

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
//        $this->drawEdges($edges);
        $lines = $this->getLines($edges); /** @var Imagick[] $lines */

        //выделяем слова
//        $stacked = new Imagick();
        $line2words = [];
        for($i = 0; $i < count($lines); ++$i){
            $wordEdges = $this->getWordEdges($lines[$i], $edges[$i]);
            $words = $this->getWords($wordEdges, $lines[$i]);
            $line2words[] = $words;
            break;
//            $this->render($lines[$i]);
//            $this->render($words[4]);
//            $this->drawVerticalEdges($wordEdges, $lines[$i], false);
//            $stacked->addimage($lines[$i]);
        }

//        $this->render($line2words[0][4]);
        $letterEdges = $this->getLetterEdges($line2words[0][4]);
        $this->drawVerticalEdges($letterEdges, $line2words[0][4], true, true);

//        $stacked->resetiterator();
//        $result = $stacked->appendimages(true);
//        $this->render($result);
//        $this->render();
//        $this->drawEdges($edges, $result);
    }

    public function getLetterEdges(Imagick $word){
        $m = $this->_imageToMatrix($word);
        $cols = Alg::getAv($m);

        $word->setimagepage(0,0,0,0);
        $d = self::FONT_KOEF * $word->getimageheight(); //средняя ширина символа

        //первый набор границ - всё подряд
        $edges0 = []; //границы
        $i = 0;
        $w = count($cols);
        while($i < $w - 1){
            $slice = array_slice($cols, $i + 1, $d, true);
            $min = min($slice);
            $i = array_search($min, $slice);
            $edges0[] = $i;
        }

        ini_set('xdebug.var_display_max_children', -1);

        //просеиваем границы
        $totalAv = array_sum($cols) / $w;
        $b = self::k5 * $totalAv; //граница яркости
        $cols[-2] = $cols[-1] = $cols[$w] = $cols[$w + 1] = $cols[$w + 2] = 0;
        $edges1 = [];
        for($i = 0; $i < count($edges0); ++$i){
            $edge = $edges0[$i];
            if($cols[$edge] < $b && ($cols[$edge - 2] > $b || $cols[$edge + 2] > $b)){
                $edges1[] = $edge;
            }
        }

        return $edges1;
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
            $level = $one['top'] - self::TOP_BLUR;
            $draw->line(0, $level, $this->_w, $level);
        }

        $draw->setstrokecolor(new ImagickPixel('red'));
        foreach($edges as $one){
            $level = $one['bottom'] + self::BOTTOM_BLUR;
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
                $level = $one['left'] - self::LEFT_BLUR;
                $draw->line($level, 0, $level, $height);
            }

            $draw->setstrokecolor(new ImagickPixel('red'));
            foreach($edges as $one){
                $level = $one['right'] + self::RIGHT_BLUR;
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
$i->analyze();