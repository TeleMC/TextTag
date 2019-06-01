<?php
namespace TextTag;

use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\Player;
use pocketmine\utils\UUID;

class FloatingText extends Position {
    private $plugin;
    private $eid;
    private $uuid;
    private $text;
    private $pos;

    public function __construct(TextTag $plugin, Position $pos, string $text) {
        parent::__construct($pos->x, $pos->y, $pos->z, $pos->level);
        $this->plugin = $plugin;
        $this->pos = $pos;
        $this->eid = Entity::$entityCount++;
        $this->uuid = UUID::fromRandom();
        $this->text = $text;
    }

    public function getId() {
        return $this->eid;
    }

    public function getText() {
        return $this->text;
    }

    public function setText(string $value) {
        $this->text = $velue;
    }

    public function spawnToAll() {
        foreach ($this->level->getPlayers() as $player) {
            $this->spawnTo($player);
        }
    }

    public function spawnTo(Player $player) {
        $pk = new AddPlayerPacket();
        $pk->uuid = $this->uuid;
        $pk->username = $this->text;
        $pk->entityRuntimeId = $this->eid;
        $pk->position = $this->pos;
        $pk->item = new Item(0, 0);
        $meta[Entity::DATA_SCALE] = [Entity::DATA_TYPE_FLOAT, 0.001];
        $pk->metadata = $meta;
        $player->dataPacket($pk);
        $pk = new PlayerListPacket();
        $pk->type = PlayerListPacket::TYPE_ADD;
        $pk->entries = [PlayerListEntry::createAdditionEntry($this->uuid, $this->eid, $this->text, new Skin("Standard_Custom", str_repeat("\x00", 8192)))];
        $player->dataPacket($pk);
        $pk = new PlayerListPacket();
        $pk->type = PlayerListPacket::TYPE_REMOVE;
        $pk->entries = [PlayerListEntry::createRemovalEntry($this->uuid)];
        $player->dataPacket($pk);
    }

    public function despawnFromAll() {
        foreach ($this->level->getPlayers() as $player) {
            $this->despawnFrom($player);
        }
    }

    public function despawnFrom(Player $player) {
        $pk = new RemoveEntityPacket();
        $pk->entityUniqueId = $this->eid;
        $player->dataPacket($pk);
    }
}
