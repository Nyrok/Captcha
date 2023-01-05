<?php

namespace Captcha;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\handler\InGamePacketHandler;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\AvailableActorIdentifiersPacket;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\BiomeDefinitionListPacket;
use pocketmine\network\mcpe\protocol\ChunkRadiusUpdatedPacket;
use pocketmine\network\mcpe\protocol\ClientCacheStatusPacket;
use pocketmine\network\mcpe\protocol\ClientToServerHandshakePacket;
use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use pocketmine\network\mcpe\protocol\InventoryContentPacket;
use pocketmine\network\mcpe\protocol\InventorySlotPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\ServerToClientHandshakePacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\TickSyncPacket;
use pocketmine\network\mcpe\protocol\types\inventory\NormalTransactionData;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener
{
    private array $cache = [];
    private Config $config;

    protected function onEnable(): void
    {
        $this->saveDefaultConfig();
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $packIdList = $this->getServer()->getResourcePackManager()->getPackIdList();
        if (!in_array("6021deea-a574-49ef-9ff3-1b02fa3b742b", $packIdList)) {
            $this->getLogger()->emergency("Vous n'avez pas le resource pack sur votre serveur ! Veuillez contacter Nyrok#1337 ou Zeyroz#0001 sur Discord !");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
        if (!$this->getServer()->getPluginManager()->getPlugin("FormAPI")) {
            $this->getLogger()->emergency("Vous n'avez pas FormAPI sur votre serveur !");
            $this->getServer()->getPluginManager()->disablePlugin($this);

        }
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onPacketSend(DataPacketSendEvent $event): void {
        foreach ($event->getTargets() as $target){
            foreach ($event->getPackets() as $pk){
                if(isset($this->cache[$target->getDisplayName()])){
                    if(match ($pk::class) {
                        ServerToClientHandshakePacket::class,
                        PlayStatusPacket::class,
                        ResourcePacksInfoPacket::class,
                        ResourcePackStackPacket::class,
                        ResourcePackDataInfoPacket::class,
                        ResourcePackChunkDataPacket::class,
                        StartGamePacket::class,
                        AvailableActorIdentifiersPacket::class,
                        BiomeDefinitionListPacket::class,
                        UpdateAttributesPacket::class,
                        InventoryContentPacket::class,
                        InventorySlotPacket::class,
                        MobEquipmentPacket::class,
                        CraftingDataPacket::class,
                        PlayerListPacket::class,
                        ChunkRadiusUpdatedPacket::class,
                        NetworkChunkPublisherUpdatePacket::class,
                        AdventureSettingsPacket::class,
                        AvailableCommandsPacket::class,
                        ModalFormRequestPacket::class,
                        => false,
                        default => true,
                    }){
                        continue;
                    }
                }
                if($pk instanceof InventoryTransactionPacket and $pk->trData instanceof NormalTransactionData){
                    $actions = $pk->trData->getActions();
                    if(count($actions) < 50) return;
                    $packet = InventoryTransactionPacket::create($pk->requestId, $pk->requestChangedSlots, NormalTransactionData::new(array_slice($actions, 0, 50)));
                    $event->cancel();
                    $target->disconnect("§cCaptcha: §4".count($actions)." actions from InventoryTransactionPacket");
                }
            }
        }
    }

    public function onPacketReceive(DataPacketReceiveEvent $event): void
    {
        $return = match ($event->getPacket()::class) {
            ResourcePackChunkRequestPacket::class,
            LoginPacket::class,
            ClientToServerHandshakePacket::class,
            ClientCacheStatusPacket::class,
            ResourcePackClientResponsePacket::class,
            RequestChunkRadiusPacket::class,
            TickSyncPacket::class,
            SetLocalPlayerAsInitializedPacket::class,
            ModalFormResponsePacket::class
                => true,
            default => false,
        };
        if ($return) return;
        $pk = $event->getPacket();
        if (isset($this->cache[$event->getOrigin()->getDisplayName()])) {
            $event->cancel();
        }
        if($pk instanceof InventoryTransactionPacket and $pk->trData instanceof NormalTransactionData){
            $actions = $pk->trData->getActions();
            if(count($actions) < 50) return;
            $packet = InventoryTransactionPacket::create($pk->requestId, $pk->requestChangedSlots, NormalTransactionData::new(array_slice($actions, 0, 50)));
            $event->cancel();
            if(!$event->getOrigin()->getHandler()->handleInventoryTransaction($packet))
                if($event->getOrigin()->getPlayer() instanceof Player)
                    $event->getOrigin()->getPlayer()->disconnect("§cCaptcha: §4".count($actions)." actions from InventoryTransactionPacket");
            $this->getLogger()->debug($event->getOrigin()->getDisplayName(). " a tenté d'envoyer ". count($actions)." actions ".$packet->getName());
        }
    }

    public function onPreLogin(PlayerPreLoginEvent $event): void
    {
        $this->cache[$event->getPlayerInfo()->getUsername()] = 3;
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $this->captcha($event->getPlayer());
        $event->getPlayer()->setImmobile(true);
    }

    public function captcha(Player $player, string $previous = ""): void
    {
        $base = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $captcha = "";
        if (empty($previous)) {
            for ($i = 1; $i <= 3; $i++) {
                $letter = substr(str_shuffle($base), 0, 1);
                $captcha .= $letter;
            }
        } else {
            $captcha .= implode('', str_split($previous));
        }
        $form = new SimpleForm(function (Player $player, $data) use ($captcha): void {
            match ($data) {
                "confirm" => $this->validateCaptcha($player, $captcha),
                default => $this->captcha($player, $captcha),
            };
        });
        $form->setTitle("§c/!\ Captcha /!\ ");
        $tries = $this->cache[$player->getName()];
        $form->setContent("§cIl vous reste §l$tries §r§cessais\n§7Made by §b@Nyrok10 §7and §bZeyroz#0001");
        $i = 1;
        foreach (str_split($captcha) as $c) {
            $form->addButton("§c".match($i){
                1 => "Première Lettre",
                2 => "Seconde Lettre",
                default => "Troisième Lettre",
            }, SimpleForm::IMAGE_TYPE_PATH, "Captcha/$c");
            $i++;
        }
        $form->addButton("§aPasser à la validation", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/confirm", "confirm");
        $player->sendForm($form);
    }

    private function validateCaptcha(Player $player, string $captcha): void
    {
        $this->cache[$player->getName()]--;
        $form = new CustomForm(function (Player $player, $data) use ($captcha): void {
            if (strtoupper($data[0] ?? "") !== $captcha) {
                if($this->cache[$player->getName()] > 0){
                    $this->captcha($player);
                    return;
                }
                $player->kick($this->getConfig()->get("kick-message", "§cVous avez raté le Captcha"));
            } else {
                $player->sendMessage($this->getConfig()->get("captcha-success", "§aVous avez réussi le Captcha"));
                $player->setImmobile(false);
            }
            unset($this->cache[$player->getName()]);
        });
        $form->addInput("§fEntrez le §cCaptcha §fvu précédemment", "Made by @Nyrok10 and Zeyroz#0001");
        $player->sendForm($form);
    }
}