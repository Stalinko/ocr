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
     * Подсчет средних значений по столбцам
     * @param $m
     * @param bool $byCols
     * @return array
     */
    public static function getAv($m, $byCols = true){
        $cols = [];
        for($i = 0; $i < count($m[0]); ++$i){
            $sum = 0;
            for($j = 0; $j < count($m); ++$j){
                $sum += $m[$j][$i];
            }
            $cols[] = 1 - $sum / count($m);
        }

        return $cols;
    }
}