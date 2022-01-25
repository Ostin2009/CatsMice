<?php

error_reporting(-1);

$catsAmount = 2;
$miceAmount = 4;
$cats = [];
$mice = [];
$roundsCounter = 100;
$fieldSize = 30;
$takenPositions = [];

//занятые позиции
function getTakenPositions($animalsOne, $animalsTwo = []){
    
    $animals = array_merge($animalsOne, $animalsTwo);
    $takenPositions = [];

    foreach ($animals as $animal){
        $takenPositions[] = $animal->getPosition();
    }

    return $takenPositions;
}

//отрисовка поля
function drawField($cats, $mice, $fieldSize){
    
    $catsPositions = getTakenPositions($cats);
    $micePositions = getTakenPositions($mice);
    
    //верхняя граница поля
    echo "<pre>";
    for($i = -1; $i <= $fieldSize; $i++){
        echo "_";
    }
    echo "<br>";

    //поле и боковые границы поля
    for($i = 0; $i <= $fieldSize; $i++){
        echo "|";
        for($j = 0; $j <= $fieldSize; $j++){
            $position = [$i, $j];
            if(in_array($position, $catsPositions)){
                //если на этой позиции кот, узнаём его номер
                foreach($cats as $cat){
                    if(!(array_diff_assoc($position, $cat->getPosition()))){
                        echo $cat->state/*, $cat->getNumber()*/;
                        break;
                    }
                }
            }elseif(in_array($position, $micePositions)){
                //если на этой позиции мышь, узнаём её номер
                foreach($mice as $mouse){
                    if(!(array_diff_assoc($position, $mouse->getPosition()))){
                        echo /*"m", */$mouse->getNumber();
                        break;
                    }
                }
            }else{
                //если на этой позиции никого нет
                echo ".";
            }
        }
        echo "|<br>";
    }
    
    //нижняя граница поля
    for($i = -1; $i <= $fieldSize; $i++){
        echo "_";
    }
    echo "</pre><br>";
    
}

//класс для направлений движения
class Direction{
    private $transition;
    private $rating = 0;

    public function __construct($x, $y){
        $this->transition = [$x, $y];
    }

    public function relocate($position){
        $position[0] += $this->transition[0];
        $position[1] += $this->transition[1];

        return $position;
    }

    public function addRating($points){
        $this->rating += $points;
    }

    public function resetRating(){
        $this->rating = 0;
    }

    public function getRating(){
        return $this->rating;
    }
}

//класс-предок для котов и мышей
abstract class Animal{
    protected $position = [];
    protected $number;
    protected $directions = [];
    //protected static $fieldSize;

    public function __construct($fieldSize, $cats, $mice){

        //$this->fieldSize = $fieldSize;
        $position = [];
        $takenPositions = getTakenPositions($cats, $mice);

        do{
            $position[0] = rand(0, $fieldSize);
            $position[1] = rand(0, $fieldSize);
        } while(in_array($position, $takenPositions));
        $this->position = $position;
    }

    public function getPosition(){
        return $this->position;
    }

    public function getNumber(){
        return $this->number;
    }

    public function move(){
        $directions = $this->directions;
        $bestDirection = new Direction(0, 0);

        foreach($directions as $direction){
            if($bestDirection->getRating() < $direction->getRating()){
                $bestDirection = $direction;
            }
        }

        $this->position = $bestDirection->relocate($this->position);
    }
}

//класс мышь
class Mouse extends Animal{
    private static $counter = 0;    
    public $dead = false;
    
    public function __construct($fieldSize, $cats, $mice){
        parent::__construct($fieldSize, $cats, $mice);
        $this->number = ++self::$counter;
        
        //$directions['stand'] = new Direction(0, 0);
        $directions['up'] = new Direction(0, 1);
        $directions['down'] = new Direction(0, -1); 
        $directions['right'] = new Direction(1, 0); 
        $directions['left'] = new Direction(-1, 0);

        $this->directions = $directions;
    }
    
    public function dies(){
        $this->dead = true;
        $this->position = [];
    }

    //оценка направлений движения для мыши
    function estimateDirections($enemies, $fieldSize, $takenPositions){
        $position = $this->position;
        $directions = $this->directions;
        $sight = 9;
        $points = [-8, -3, 3, 8];
        
        foreach($directions as $directionName => $direction){
            $direction->resetRating();
            $newPosition = $direction->relocate($position);

            foreach($enemies as $enemy){
                $enemyPosition = $enemy->getPosition();
                //если мышь видит кота
                if((abs($position[0] - $enemyPosition[0]) <= $sight) and (abs($position[1] - $enemyPosition[1]) <= $sight)){
                    //даём оценку направлению
                    if(($newPosition[0] == $enemyPosition[0]) or ($newPosition[1] == $enemyPosition[1])){
                        $direction->addRating(1);
                    }
                    if(abs($position[0] - $enemyPosition[0]) < abs($newPosition[0] - $enemyPosition[0]) or 
                    abs($position[1] - $enemyPosition[1]) < abs($newPosition[1] - $enemyPosition[1])){
                        $direction->addRating($points[3]);
                    }elseif(abs($position[0] - $enemyPosition[0]) > abs($newPosition[0] - $enemyPosition[0]) or 
                    abs($position[1] - $enemyPosition[1]) > abs($newPosition[1] - $enemyPosition[1])){
                        $direction->addRating($points[0]);
                    }
                }
            }
            //оцениваем движение относительно занятых позиций, а также краёв поля 
            if(in_array($newPosition, $takenPositions)){
                $direction->addRating(-30);
            }else{
                if($directionName == 'up'){
                    if($newPosition[1] > $fieldSize){
                        $direction->addRating(-30);
                    }else{
                        if($newPosition[1] < ($fieldSize - $newPosition[1])){
                            $direction->addRating($points[2]);
                        }elseif($newPosition[1] > ($fieldSize - $newPosition[1])){
                            $direction->addRating($points[1]);
                        }
                    }
                }
                if($directionName == 'down'){
                    if($newPosition[1] < 0){
                        $direction->addRating(-30);
                    }else{
                        if($newPosition[1] < ($fieldSize - $newPosition[1])){
                            $direction->addRating($points[1]);
                        }elseif($newPosition[1] > ($fieldSize - $newPosition[1])){
                            $direction->addRating($points[2]);
                        }
                    }
                }
                if($directionName == 'right'){
                    if($newPosition[0] > $fieldSize){
                        $direction->addRating(-30);
                    }else{
                        if($newPosition[0] < ($fieldSize - $newPosition[0])){
                            $direction->addRating($points[2]);
                        }elseif($newPosition[0] > ($fieldSize - $newPosition[0])){
                            $direction->addRating($points[1]);
                        }
                    }
                }
                if($directionName == 'left'){
                    if($newPosition[0] < 0){
                        $direction->addRating(-30);
                    }else{
                        if($newPosition[0] < ($fieldSize - $newPosition[0])){
                            $direction->addRating($points[1]);
                        }elseif($newPosition[0] > ($fieldSize - $newPosition[0])){
                            $direction->addRating($points[2]);
                        }
                    }
                }
            }
        }
        
        //$directions['stand']->resetRating();
    }
}

//класс кот
class Cat extends Animal{
    private static $counter = 0;
    private $movesCount = 1;
    
    public $sleep = false;
    public $state = 'C';

    public function __construct($fieldSize, $cats, $mice){
        parent::__construct($fieldSize, $cats, $mice);
        $this->number = ++self::$counter;
        
        //$directions['stand'] = new Direction(0, 0);
        $directions['up'] = new Direction(0, 1);
        $directions['down'] = new Direction(0, -1); 
        $directions['right'] = new Direction(1, 0); 
        $directions['left'] = new Direction(-1, 0);
        $directions['up-right'] = new Direction(1, 1);
        $directions['up-left'] = new Direction(-1, 1);
        $directions['down-right'] = new Direction(1, -1); 
        $directions['down-left'] = new Direction(-1, -1); 

        $this->directions = $directions;
    }
    
    //оценка направлений движения для кота
    public function estimateDirections($victims){
        $position = $this->position;
        $directions = $this->directions;
        $victimPosition = [];
        
        foreach($victims as $victim){
            if($victim->dead){
                continue;
            }
            if($victimPosition == []){
                $victimPosition = $victim->getPosition();
            }elseif(
                (abs(abs($position[0] - $victimPosition[0]) - abs($position[1] - $victimPosition[1]))) >
                (abs(abs($position[0] - $victim->getPosition()[0]) - abs($position[1] - $victim->getPosition()[1])))
            ){
                $victimPosition = $victim->getPosition();
            }
        }

        if($victimPosition == []){
            foreach($directions as $direction){
                $direction->resetRating();
            }
            return;
        }

        foreach($directions as $direction){
            $direction->resetRating();
            $newPosition = $direction->relocate($position);
            if((abs($newPosition[0] - $victimPosition[0]) < abs($position[0] - $victimPosition[0])) or (abs($newPosition[1] - $victimPosition[1]) < abs($position[1] - $victimPosition[1]))){
                $direction->addRating(1);
            }
            if((abs($newPosition[0] - $victimPosition[0]) < abs($position[0] - $victimPosition[0])) and (abs($newPosition[1] - $victimPosition[1]) < abs($position[1] - $victimPosition[1]))){
                $direction->addRating(1);
            }
        }


        if($this->movesCount == 8){
            $this->sleeps();
        }
        $this->movesCount++;
    }

    public function sleeps(){
        $this->sleep = true;
        $this->state = "@";
    }

    public function wakesUp(){
        $this->sleep = false;
        $this->state = "C";
        $this->movesCount = 1;
    }

}

//создаём котов
for($i = 0; $i < $catsAmount; $i++){
    $cats[] = new Cat($fieldSize, $cats, $mice);
}    

//создаём мышей
for($i = 0; $i < $miceAmount; $i++){
    $mice[] = new Mouse($fieldSize, $cats, $mice);
}

echo "Ход номер 0";
drawField($cats, $mice, $fieldSize);

//делаем ходы
for($round = 1; $round <= $roundsCounter; $round++){
    echo "Ход номер $round";
    //сначала мыши
    foreach($mice as $mouse){
        if(!$mouse->dead){
            $takenPositions = getTakenPositions($mice);
            $mouse->estimateDirections($cats, $fieldSize, $takenPositions);
            $mouse->move();    
        }
    }
    //потом коты
    foreach($cats as $cat){
        if(!$cat->sleep){
            $cat->estimateDirections($mice);
            $cat->move();
            foreach($mice as $mouse){
                if($cat->getPosition() == $mouse->getPosition()){
                    $mouse->dies();
                    $cat->sleeps();
                }
            }
        }else{
            $cat->wakesUp();
        }
    }

    drawField($cats, $mice, $fieldSize);
}


/*
$takenPositions = getTakenPositions($cats);

foreach($takenPositions as $pos){
    var_dump ($pos);
    echo "<br>";
}

$takenPositions = getTakenPositions($mice);

foreach($takenPositions as $pos){
    var_dump ($pos);
    echo "<br>";
}
*/