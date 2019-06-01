<?php
namespace TextTag;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\level\Level;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;

class TextTag extends PluginBase {
    public $pre = "§e•";
    //public $pre = "§l§e[ §f시스템 §e]§r§e";
    public $tag = [];
    public $tag_pos = [];

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        @mkdir($this->getDataFolder());
        $this->config = new Config($this->getDataFolder() . "tag.yml", Config::YAML, ["distance" => 25, "tag" => []]);
        $this->data = $this->config->getAll();
        $this->loadTag();
    }

    public function loadTag() {
        if (count($this->data["tag"]) == 0)
            return;
        else {
            $count = 0;
            foreach ($this->data["tag"] as $key => $value) {
                $xyz = explode(":", $key);
                $level = $this->getServer()->getLevelByName($xyz[3]);
                $title = str_replace("(n)", "\n", $value);
                $this->tag[$count] = new FloatingText($this, new Position($xyz[0] + 0.5, $xyz[1], $xyz[2] + 0.5, $level), $title);
                $this->tag_pos[(int) $xyz[0] . ":" . (int) $xyz[2] . ":" . $level->getName()] = $this->tag[$count];
                $count++;
            }
        }
    }

    public function onDisable() {
        $this->save();
    }

    public function save() {
        $this->config->setAll($this->data);
        $this->config->save();
    }

    public function getNearbyTag(Position $pos) {
        if ($this->data["distance"] == 0)
            return $this->tag;
        $minX = ((int) floor($pos->x - $this->data["distance"]));
        $maxX = ((int) floor($pos->x + $this->data["distance"]));
        $minZ = ((int) floor($pos->z - $this->data["distance"]));
        $maxZ = ((int) floor($pos->z + $this->data["distance"]));
        $list = [];
        for ($x = $minX; $x <= $maxX; ++$x) {
            for ($z = $minZ; $z <= $maxZ; ++$z) {
                $key = $x . ":" . $z . ":" . $pos->getLevel()->getName();
                if (isset($this->tag_pos[$key]))
                    array_push($list, $this->tag_pos[$key]);
            }
        }
        return $list;
    }

    public function sendTag(Player $player) {
        if (count($this->tag) == 0)
            return;
        foreach ($this->tag as $key => $value) {
            $this->tag[$key]->spawnTo($player);
        }
    }

    public function addTag(float $x, float $y, float $z, string $world, string $title) {
        if (isset($this->data["tag"]["{$x}:{$y}:{$z}:{$world}"]))
            return false;
        if (!isset($this->data["tag"]))
            $this->data["tag"] = [];
        $this->data["tag"]["{$x}:{$y}:{$z}:{$world}"] = $title;
        $level = $this->getServer()->getLevelByName($world);
        $title = str_replace("(n)", "\n", $title);
        $this->tag[$this->getNumber($x, $y, $z, $world)] = new FloatingText($this, new Position($x + 0.5, $y, $z + 0.5, $level), $title);
        $this->tag_pos[(int) $x . ":" . (int) $z . ":" . $level->getName()] = $this->tag[$this->getNumber($x, $y, $z, $world)];
        $this->sort();
        return true;
    }

    public function getNumber(float $x, float $y, float $z, string $world) {
        if (!isset($this->data["tag"]["{$x}:{$y}:{$z}:{$world}"]))
            return null;
        else {
            $i = 0;
            foreach ($this->data["tag"] as $key => $value) {
                if ($key == "{$x}:{$y}:{$z}:{$world}")
                    break;
                $i++;
            }
            return $i;
        }
    }

    public function sort() {
        if (count($this->tag) <= 0)
            return;
        else {
            $list = [];
            for ($i = 0; $i <= count($this->tag); $i++) {
                if (!isset($this->tag[$i]))
                    continue;
                else
                    array_push($list, $this->tag[$i]);
            }
            $this->tag = $list;
        }
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, $args): bool {
        if ($cmd->getName() == "태그") {
            if (!$sender->isOp()) {
                $sender->sendMessage("{$this->pre} 권한이 없습니다.");
                return false;
            } else {
                if (!isset($args[0])) {
                    $sender->sendMessage("--- 태그 도움말 1 / 1 ---");
                    $sender->sendMessage("{$this->pre} /태그 생성 <태그> | 태그 생성 모드로 돌입합니다.");
                    $sender->sendMessage("{$this->pre} /태그 제거 <태그 번호> | 태그를 제거합니다.");
                    $sender->sendMessage("{$this->pre} /태그 취소 | 모든 작업을 중지합니다.");
                    $sender->sendMessage("{$this->pre} /태그 거리 <거리> | 태그 시야거리를 조절합니다.");
                    $sender->sendMessage("{$this->pre} /태그 목록 <인덱스> | 태그 목록을 확인합니다.");
                    return false;
                }
                switch ($args[0]) {
                    case "생성":
                        if (!isset($args[1])) {
                            $sender->sendMessage("{$this->pre} /태그 생성 <태그> | 태그 생성 모드로 돌입합니다.");
                            return false;
                        }
                        unset($args[0]);
                        $title = implode(" ", $args);
                        $this->mode[$sender->getName()] = "생성";
                        $this->title[$sender->getName()] = $title;
                        $sender->sendMessage("{$this->pre} 태그 생성 모드에 돌입하였습니다.");
                        break;

                    case "제거":
                        if (!isset($args[1])) {
                            $sender->sendMessage("{$this->pre} /태그 제거 <태그 번호> | 태그를 제거합니다.");
                            return false;
                        }
                        if (!is_numeric($args[1])) {
                            $sender->sendMessage("{$this->pre} 태그 번호는 숫자여야합니다.");
                            return false;
                        }
                        $count = 0;
                        $del = [];
                        foreach ($this->data["tag"] as $key => $value) {
                            $del[$count] = $key;
                            $count++;
                        }
                        if (!isset($del[$args[1]])) {
                            $sender->sendMessage("{$this->pre} 해당 태그가 없습니다.");
                            return false;
                        }
                        $xyz = explode(":", $del[$args[1]]);
                        $tag = $this->getTag($xyz[0], $xyz[1], $xyz[2], $xyz[3]);
                        if ($this->delTag($xyz[0], $xyz[1], $xyz[2], $xyz[3])) {
                            $sender->sendMessage("{$this->pre} 성공적으로 {$tag} 태그를 제거하였습니다.");
                            return true;
                        } else {
                            $sender->sendMessage("{$this->pre} 태그 제거에 실패하였습니다.");
                            return false;
                        }
                        break;

                    case "취소":
                        unset($this->mode[$sender->getName()]);
                        $sender->sendMessage("{$this->pre} 모든 작업을 중단하였습니다.");
                        break;

                    case "목록":
                        if (count($this->data["tag"]) == 0) {
                            $sender->sendMessage("--- 태그 목록 1 / 1 ---");
                            $sender->sendMessage("{$this->pre} 태그가 존재하지 않습니다.");
                            return true;
                        }
                        $maxpage = ceil(count($this->data["tag"]) / 5);
                        if (!isset($args[1]) || !is_numeric($args[1]) || $args[1] <= 0) {
                            $page = 1;
                        } elseif ($args[1] > $maxpage) {
                            $page = $maxpage;
                        } else {
                            $page = $args[1];
                        }
                        $tag = "";
                        $count = 0;
                        foreach ($this->data["tag"] as $key => $value) {
                            if ($page * 5 - 5 <= $count and $count < $page * 5) {
                                $title = str_replace("(n)", "§r§f(n)", $value);
                                $tag .= "§l§e[§f{$count}번§e] §r§e[ §f{$title} §r§e]\n";
                                $count++;
                            } else {
                                $count++;
                                continue;
                            }
                        }
                        $sender->sendMessage("--- 태그 목록 {$page} / {$maxpage} ---");
                        $sender->sendMessage($tag);
                        break;

                    case "거리":
                        if (!isset($args[1]) || !is_numeric($args[1]) || $args[1] < 0 || 50 < $args[1]) {
                            $sender->sendMessage("{$this->pre} 거리는 0 ~ 50의 정수형태여야합니다.");
                            return false;
                        }
                        $this->data["distance"] = $args[1];
                        if ($this->data["distance"] == 0) {
                            foreach ($this->tag as $key => $value) {
                                $value->spawnToAll();
                            }
                        }
                        $sender->sendMessage("{$this->pre} 성공적으로 시야거리를 설정하였습니다.");
                        break;

                    default:
                        $sender->sendMessage("--- 태그 도움말 1 / 1 ---");
                        $sender->sendMessage("{$this->pre} /태그 생성 <태그> | 태그 생성 모드로 돌입합니다.");
                        $sender->sendMessage("{$this->pre} /태그 제거 <태그 번호> | 태그를 제거합니다.");
                        $sender->sendMessage("{$this->pre} /태그 취소 | 모든 작업을 중지합니다.");
                        $sender->sendMessage("{$this->pre} /태그 거리 <거리> | 태그 시야거리를 조절합니다.");
                        $sender->sendMessage("{$this->pre} /태그 목록 <인덱스> | 태그 목록을 확인합니다.");
                        break;
                }
                return true;
            }
            return false;
        }
        return false;
    }

    public function getTag(float $x, float $y, float $z, string $world) {
        if (!isset($this->data["tag"]["{$x}:{$y}:{$z}:{$world}"]))
            return null;
        else
            return $this->data["tag"]["{$x}:{$y}:{$z}:{$world}"];
    }

    public function delTag(float $x, float $y, float $z, string $world) {
        if (!isset($this->data["tag"]["{$x}:{$y}:{$z}:{$world}"]))
            return false;
        else {
            $this->tag[$this->getNumber($x, $y, $z, $world)]->despawnFromAll();
            unset($this->tag[$this->getNumber($x, $y, $z, $world)]);
            unset($this->tag_pos[$x . ":" . $z . ":" . $world]);
            unset($this->data["tag"]["{$x}:{$y}:{$z}:{$world}"]);
            $this->sort();
            return true;
        }
    }
}
