<?php
namespace TextTag;

use pocketmine\block\Block;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;

class EventListener implements Listener {
    public function __construct(TextTag $plugin) {
        $this->plugin = $plugin;
    }

    public function onJoin(PlayerJoinEvent $ev) {
        $player = $ev->getPlayer();
        if ($this->plugin->data["distance"] == 0)
            $this->plugin->sendTag($player);
    }

    public function onMove(PlayerMoveEvent $ev) {
        $player = $ev->getPlayer();
        if ($this->plugin->data["distance"] == 0)
            return;
        if (!isset($this->time[$player->getName()]))
            $this->time[$player->getName()] = time();
        if (time() - $this->time[$player->getName()] < 1)
            return;
        $this->time[$player->getName()] = time();
        $tags = $this->plugin->getNearbyTag($player);
        foreach ($tags as $tag) {
            if (!isset($this->plugin->near[$player->getId()][$tag->getId()])) {
                $tag->spawnTo($player);
                $this->plugin->near[$player->getId()][$tag->getId()] = $tag;
            }
        }
        if (!isset($this->plugin->near[$player->getId()]))
            return;
        foreach ($this->plugin->near[$player->getId()] as $key => $tag) {
            if ($tag->distance($player) > $this->plugin->data["distance"]) {
                $tag->despawnFrom($player);
                unset($this->plugin->near[$player->getId()][$key]);
            }
        }
    }

    public function onTouch(PlayerInteractEvent $ev) {
        $player = $ev->getPlayer();
        $block = $ev->getBlock();
        $x = $block->getFloorX();
        $y = $block->getFloorY();
        $z = $block->getFloorZ();
        $world = $player->getLevel()->getName();
        if (isset($this->plugin->mode[$player->getName()])) {
            if ($this->plugin->mode[$player->getName()] == "생성") {
                if ($this->plugin->addTag($x, $y, $z, $world, $this->plugin->title[$player->getName()])) {
                    $player->sendMessage("{$this->plugin->pre} 성공적으로 태그를 생성하였습니다.");
                    unset($this->plugin->mode[$player->getName()]);
                    unset($this->plugin->title[$player->getName()]);
                    return;
                }
            }
        }
    }
}
