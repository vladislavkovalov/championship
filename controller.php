<?php

header('Content-Type: application/json');

class Team {
    
    public $name;
    public $id;
    
    private $pts             = 0;
    private $won             = 0;
    private $drawn           = 0;
    private $lost            = 0;
    private $gf              = 0;
    private $ga              = 0;
    private $gd              = 0;
    private $played          = 0;
    private $percentByWeek   = 0;
    private $matchResultHtml = '';
    
    public function __construct(string $name, int $id)
    {
        $this->name = $name;
        $this->id = $id;
    }
    
    public function __call(string $name, array $arguments) {
        if(!property_exists('Team', $name)) {
            return $this;
        }
        
        $this->$name = (int)$arguments[0];
        
        return $this;
    }
    
    public function __get(string $name) {
        if(!property_exists('Team', $name)) {
            return '';
        }
        
        return $this->$name;
    }
    
    public function processMatch($myScore, $opponentScore, $matchResultHtml)
    {
        $this->played++;
        $this->gf += $myScore;
        $this->ga += $opponentScore;
        $this->gd += ($myScore - $opponentScore);
        $this->matchResultHtml = $matchResultHtml;
        
        if ($myScore > $opponentScore) {
            $this->pts += 3;
            $this->won++;
        } else if ($myScore < $opponentScore) {
            $this->lost++;
        } else {
            $this->pts++;
            $this->drawn++;
        }
    }
    
    public function setPercentByWeek(float $percent) {
        $this->percentByWeek = $percent;
        return $this;
    }
    
    public function getResult() {
        return '<tr id="'.$this->id.'">
                    <td data-item="name">'.$this->name.'</td>
                    <td data-item="pts">'.$this->pts.'</td>
                    <td data-item="played">'.$this->played.'</td>
                    <td data-item="won">'.$this->won.'</td>
                    <td data-item="drawn">'.$this->drawn.'</td>
                    <td data-item="lost">'.$this->lost.'</td>
                    <td data-item="gf">'.$this->gf.'</td>
                    <td data-item="ga">'.$this->ga.'</td>
                    <td data-item="gd">'.$this->gd.'</td>

                    '.$this->matchResultHtml.'
                </tr>';
    }
    
    public function getPredictionResult() {
        return '<tr>
                    <td>'.$this->name.'</td>
                    <td>'.$this->percentByWeek.'%</td>
                </tr>';
    }
    
}

class ProbabilityOfVictory {
    
    private static $instance;
    
    private $teams      = [];
    private $ptsByTeam  = [];
    private $allPts     = 0;
    
    protected function __construct() { }
    
    protected function __clone() { }
    
    public static function getInstance(): ProbabilityOfVictory
    {
        if (!isset(self::$instance)) {
            self::$instance = new static();
        }
        
        return self::$instance;
    }
    
    public function setTeams(array $teams) {
        
        $this->teams = [];
        
        foreach ($teams as $team) {
            if(!($team instanceof Team)) {
                echo 'Wrong team type';
                die;
            }
            $this->teams[] = $team;
        }
        
        return $this;
    }
    
    public function isTeamSet() {
        return count($this->teams) ? true : false;
    }
    
    public function process(int $currentWeek) {
        
        $this->allPts = 0;
        
        foreach ($this->teams as $team) {
            $this->allPts += $team->pts;
            $this->ptsByTeam[$team->id] = $team->pts;
        }
        
        arsort($this->ptsByTeam);
        
        foreach ($this->teams as $team) {
            $team->setPercentByWeek(round(((100 * $team->pts) / $this->allPts), 2));
        }
    }
    
    public function getSortTeamIds() {
        return $this->ptsByTeam;
    }
    
}

class ViewGenerator {
    
    private static $headers = [
        1 => '<tr>
                <th colspan=9>League Table</th>
                <th colspan=3>Match Results</th>
              </tr>
              <tr>
                <td>TEAMS</td>
                <td>Points</td>
                <td>Played</td>
                <td>Won</td>
                <td>Drawn</td>
                <td>Lost</td>
                <td>GF</td>
                <td>GA</td>
                <td>GD</td>

                <td colspan=3>%d Weeks Match Result</td>
              </tr>',
        2 => '<tr>
                <th colspan=2>%d Weeks Predictions of Championship</th>
              </tr>'
    ];
    
    public static function getHeaderByTableId($id = 1, $week = 1) {
        return sprintf(self::$headers[$id], $week);
    }
}

class Simulator {
    
    private $allPossibleMatches = false;
    private $allChampionship    = false;
    private $allMatchCreated    = false;
    private $matchInWeek        = 0;
    private $currentWeek        = 1;
    private $teams              = [];
    private $matchByWeek        = [];
    private $result             = [];
    
    public function __construct($currentWeek) {
        if($currentWeek == 'all') {
            $this->allChampionship = true;
            return;
        }
        
        $this->currentWeek = $currentWeek;
    }
    
    public function createTeams(array $teams)
    {
        foreach ($teams as $key => $team) {
            if(!isset($team['name'])) {
                exit;
            }
            
            $tmpTeam = new Team($team['name'], $key);
            $tmpTeam
                ->pts($team['pts'])
                ->won($team['won'])
                ->drawn($team['drawn'])
                ->lost($team['lost'])
                ->gf($team['gf'])
                ->ga($team['ga'])
                ->gd($team['gd'])
                ->played($team['played']);
            
            $this->teams[$key] = $tmpTeam;
            
            if($this->allChampionship) {
                $this->currentWeek = (int)$team['played'] + 1;
            }
        }
    }
    
    public function simulate()
    {
        
        if (
            !$qty = count($this->teams)
            OR $qty % 2
        ) {
            echo 'No teams found or their number is not matched!';
            exit;
        }
        
        $maxWeek = (($qty - 1) * $qty) / 2;
        
        if($this->currentWeek > $maxWeek) {
            return false;
        }
        
        $this->matchInWeek = $qty / 2;
        
        if($this->allPossibleMatches === false) {
            $this->allPossibleMatches();
        }
        
        if(!$this->allMatchCreated) {
            for ($i = 1; $i <= $maxWeek; $i++) {
                $this->createMatchesByWeek($i);
            }
            $this->allMatchCreated = true;
        }
        
        $this->processMatchesByWeek();
        
        $probabilityOfVictory = ProbabilityOfVictory::getInstance();
        
        if(!$probabilityOfVictory->isTeamSet()) {
            $probabilityOfVictory->setTeams($this->teams);
        }
        
        $probabilityOfVictory->process($this->currentWeek);
        
        $this->result[1] = ViewGenerator::getHeaderByTableId(1, $this->currentWeek);
        $this->result[2] = ViewGenerator::getHeaderByTableId(2, $this->currentWeek);
        
        foreach ($probabilityOfVictory->getSortTeamIds() as $teamId => $teamPts) {
            $this->result[1] .= $this->teams[$teamId]->getResult();
            $this->result[2] .= $this->teams[$teamId]->getPredictionResult();
        }
        
        $this->result['next_week'] = ++$this->currentWeek;
        
        return $this->allChampionship;
    }
    
    public function getResult() {
        return $this->result;
    }
    
    private function simulateMatchResult() {
        return [rand(0, 5), rand(0, 5)];
    }
    
    private function allPossibleMatches() {
        $this->allPossibleMatches = [];
        
        foreach ($this->teams as $key => $team) {
            foreach ($this->teams as $innerKey => $innerTeam) {
                
                if($key === $innerKey) {
                    continue;
                }
                
                $this->allPossibleMatches[] = [
                    $key,
                    $innerKey
                ];
                
            }
        }
    }
    
    private function createMatchesByWeek(int $week) {
        
        $this->matchByWeek[$week] = [];
        
        foreach ($this->allPossibleMatches as $key => $match) {
            if(count($this->matchByWeek[$week]) >= $this->matchInWeek) {
                break;
            }
            
            $isMatchUniq = true;
            
            foreach ($this->matchByWeek[$week] as $item) {
                $isMatchUniq = $item[0] !== $match[0] && $item[1] !== $match[1] && $item[1] !== $match[0] && $item[0] !== $match[1];
            }
            
            if($isMatchUniq) {
                $this->matchByWeek[$week][] = $match;
                unset($this->allPossibleMatches[$key]);
            }
        }
    }
    
    private function processMatchesByWeek() {
        foreach ($this->matchByWeek[$this->currentWeek] as &$item) {
            $matchResult = $this->simulateMatchResult();
            $item['result'] = [$matchResult[0], $matchResult[1]];
            
            $matchResultHtml = '<td>'.$this->teams[$item[0]]->name.'</td>
                        <td>'.$matchResult[0].' : '.$matchResult[1].'</td>
                        <td>'.$this->teams[$item[1]]->name.'</td>';
            
            $this->teams[$item[0]]->processMatch($matchResult[0], $matchResult[1], $matchResultHtml);
            $this->teams[$item[1]]->processMatch($matchResult[1], $matchResult[0], $matchResultHtml);
        }
    }
    
}



if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && $_SERVER['REQUEST_METHOD'] == 'POST')
{
    
    if(
        !isset($_POST['current_week'])
        OR !$currentWeek = $_POST['current_week']
        OR !$teams = $_POST['teams']
        OR !is_array($teams)
    ) {
        exit;
    }
    
    $simulator = new Simulator($currentWeek);
    $simulator->createTeams($teams);
    
    $loop = true;
    while ($loop) {
        $loop = $simulator->simulate();
    }
    
    echo json_encode($simulator->getResult());
    
}

