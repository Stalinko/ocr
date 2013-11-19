<?php

abstract class Alg{
    /**
     * Выделение строк в тексте
     * @param array $rows Массив средних яркостей строк. 0 - белый.
     * @param int $k1 0..1 Коэффициент погрешности для верхней границы
     * @param int $k2 0..1 Коэффициент погрешности для нижней границы
     * @return array Массив выделенных строк [['top' => t1, 'bottom' => b1], ...]
     */
    public static function getLines($rows, $k1, $k2){
        $totalAv = array_sum($rows) / count($rows);
        $t = $totalAv * $k1;
        $b = $totalAv * $k2;

        $edges = [];
        $n = count($rows);
        $rows[-1] = $rows[-2] = $rows[$n] = $rows[$n + 1] = $rows[$n + 2] = 0;
        $wasTop = false;
        for($i = 0; $i < $n; ++$i){
            if(!$wasTop){
                if($rows[$i - 2] < $t && $rows[$i - 1] < $t & $rows[$i] > $b && $rows[$i + 1] > $b && $rows[$i + 2] > $b && $rows[$i + 3] > $b){
                    $edges[$i] = 'top';
                    $i += 30;
                    $wasTop = true;
                }
            }else{
                if(($rows[$i] > $t && $rows[$i + 1] < $b) || ($rows[$i + 1] < $b && $rows[$i + 2] < $b && $rows[$i + 3] < $b)){
                    $edges[$i] = 'bottom';
                    $wasTop = false;
                }
            }
        }

        $result = [];
        while(list($top,) = each($edges)){
            list($bottom,) = each($edges);
            $result[] = ['top' => $top, 'bottom' => $bottom];
        }
        return $result;
    }

    /**
     * Рассчёт границ слов по матрице изображения
     * @param array $m Матрица изображения. 0 - белый. 1 - черный.
     * @param $k3 0..1 Коэффициент погрешности для левой границы
     * @param $k4 0..1 Коэффициент погрешности для правой границы
     * @return array Список границ слов: [['left' => l1, 'right' => r1], ...]
     */
    public static function getWordEdges($m, $k3, $k4){
        $cols = self::getAv($m); //подсчитываем средние яркости столбцов

        //общая средняя яркость
        $totalAv = array_sum($cols) / count($cols);
        $l = $k3 * $totalAv;
        $r = $k4 * $totalAv;

        //рассчет границ слов
        $edges = [];
        $n = count($cols);
        $cols[-2] = $cols[-1]
            = $cols[$n + 1] = $cols[$n + 2] = $cols[$n + 3] = $cols[$n + 4]
            = 0;
        $wasLeft = false;
        for($i = 0; $i < $n; ++$i){
            if(!$wasLeft){
                if($cols[$i - 1] < $l && $cols[$i] > $l && $cols[$i + 1] > $l){
                    $edges[$i] = 'left';
                    $i += 5;
                    $wasLeft = true;
                }
            }else{
                if($cols[$i - 2] > $r && $cols[$i - 1] > $r && $cols[$i] < $r && $cols[$i + 1] < $r && $cols[$i + 2] < $r
                    && $cols[$i + 3] < $r && $cols[$i + 4] < $r){
                    $edges[$i] = 'right';
                    $wasLeft = false;
                }
            }
        }

        $result = [];
        while(list($left,) = each($edges)){
            list($right,) = each($edges);
            $result[] = ['left' => $left, 'right' => $right];
        }
        return $result;
    }

    /**
     * Рассчёт границ символов
     * @param array $m
     * @param int $fontKoef
     * @param int $k5
     * @param int $k6
     * @return array
     */
    public static function getLetterEdges($m, $fontKoef, $k5, $k6){
        $cols = Alg::getAv($m);
        $h = count($m); //высота строки(слова)
        $d = $fontKoef * $h; //средняя ширина символа

        //этап первый - грубый рассчёт всех границ
        $edges0 = []; //границы - номера столбцов из исх матрицы
        $i = 0;
        $w = count($cols);
        while($i < $w - 1){
            $slice = array_slice($cols, $i + 1, $d, true);
            $min = min($slice);
            $i = array_search($min, $slice);
            $edges0[] = $i;
        }

//        return $edges0;

        //этап второй - просеиваем границы
        $edges1 = []; //просеянные границы - номера столбцов
        $totalAv = array_sum($cols) / $w;
        $b = $k5 * $totalAv; //граница яркости
        $cols[-2] = $cols[-1] = $cols[$w] = $cols[$w + 1] = $cols[$w + 2] = 0;
        foreach($edges0 as $edge){
            if($cols[$edge] < $b && ($cols[$edge - 2] > $b || $cols[$edge + 2] > $b)){
                $edges1[] = $edge;
            }
        }

        return $edges1;
        //этап третий
        $edges2 = [];
        $h1 = ceil(0.3 * $h); //30% высоты
        $h2 = ceil(0.7 * $h); //70% высоты
        $dMin = $k6 * $h;
        $prevEdge = -$dMin;
        foreach($edges1 as $edge){
            //фильтруем по расстоянию
            $d = $edge - $prevEdge;
            $prevEdge = $edge;
            if($d < $dMin){
                continue;
            }

            //колонки текущая и соседи
            $col0 = self::_matrixCol($m, $edge - 1);
            $col1 = self::_matrixCol($m, $edge);
            $col2 = self::_matrixCol($m, $edge + 1);

            //сравниваем яркости трех частей с соседями
            $max00 = self::_getMaxInLevel($col0, 0, $h1);
            $max10 = self::_getMaxInLevel($col1, 0, $h1);
            $max20 = self::_getMaxInLevel($col2, 0, $h1);

            $max01 = self::_getMaxInLevel($col0, $h1, $h2);
            $max11 = self::_getMaxInLevel($col1, $h1, $h2);
            $max21 = self::_getMaxInLevel($col2, $h1, $h2);

            $max02 = self::_getMaxInLevel($col0, $h2, $h);
            $max12 = self::_getMaxInLevel($col1, $h2, $h);
            $max22 = self::_getMaxInLevel($col2, $h2, $h);

            $sub_cond11 = ($max00 == $max10) || ($max01 == $max11) || ($max02 == $max12);
            $sub_cond12 = ($max20 == $max10) || ($max21 == $max11) || ($max22 == $max12);

            if(!$sub_cond11 || !$sub_cond12){
                $edges2[] = $edge;
                continue;
            }

            //условие 2
            $sub_cond21 = array_sum($col1) / $h < max($col0);
            $sub_cond22 = array_sum($col1) / $h < max($col2);

            if(!$sub_cond21 || !$sub_cond22){
                $edges2[] = $edge;
                continue;
            }

            //условие 3
            $sub_cond31 = max($col1) > 2 * abs(max($col1) - max($col0));
            $sub_cond32 = max($col1) > 2 * abs(max($col1) - max($col2));

            if(!$sub_cond31 || !$sub_cond32){
                $edges2[] = $edge;
                continue;
            }
        }

        return $edges2;
    }

    private static function _matrixCol($m, $n){
        $chunks = 1;
        $ranges = [];
        $h = count($m);
        for($i = 1; $i <= $chunks; ++$i){
            $border = (int)ceil($h / $chunks * $i);
            $ranges[$border] = $n - round($chunks / 2) + $i;
        }

        $r = [];
        for($i = 0; $i < $h; ++$i){
            foreach($ranges as $border => $index){
                if($i < $border){
                    break;
                }
            }
//            echo $index, ' ';
            $r[] = isset($row[$index]) ? $row[$index] : 0;
        }
//        die;

        return $r;
    }

    /**
     * @param $col
     * @param $from
     * @param $to
     * @return array
     */
    private static function _getMaxInLevel($col, $from, $to){
        return max(array_slice($col, $from, $to - $from, true));
    }

    /**
     * Подсчет средних значений по столбцам
     * @param $m
     * @param bool $byCols
     * @return array
     */
    public static function getAv($m, $byCols = true){
        $chunks = 2;
        $ranges = [];
        $h = count($m);
        for($i = 1; $i <= $chunks; ++$i){
            $border = (int)ceil($h / $chunks * $i);
            $ranges[$border] = -round($chunks / 2) + $i - 1;
        }

        $cols = [];
        for($i = 0; $i < count($m[0]); ++$i){
            $sum = 0;
            for($j = 0; $j < count($m); ++$j){
                $offset = 0;
                foreach($ranges as $border => $offset){
                    if($j < $border){
                        break;
                    }
                }
                $index = $i + $offset;
                $sum += isset($m[$j][$index]) ? $m[$j][$index] : 1;
            }
            $cols[] = 1 - $sum / count($m);
        }

        return $cols;
    }
}