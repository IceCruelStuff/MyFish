<?php


declare(strict_types = 1);

namespace Fishing\item;

use Fishing\entity\projectile\FishingHook;
use Fishing\item\enchantment\Enchantment;
use Fishing\Fishing;
use Fishing\Session;
use Fishing\Utils;
use Fishing\utils\FishingLootTable;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\level\sound\LaunchSound;
use pocketmine\math\Vector3;
use pocketmine\Player;

class FishingRod extends Durable {
	public function __construct($meta = 0){
		parent::__construct(Item::FISHING_ROD, $meta, "Fishing Rod");
	}

	public function getMaxStackSize(): int{
		return 1;
	}

	public function getMaxDurability(): int{
		return 355; // TODO: Know why it breaks early at 65
	}

	public function onClickAir(Player $player, Vector3 $directionVector): bool{
			$session = Fishing::getInstance()->getSessionById($player->getId());
			if($session instanceof Session){
				if(!$session->fishing){
					$nbt = Entity::createBaseNBT($player->add(0, $player->getEyeHeight(), 0), $directionVector, $player->yaw, $player->pitch);

					/** @var FishingHook $projectile */
					$projectile = Entity::createEntity($this->getProjectileEntityType(), $player->getLevel(), $nbt, $player);
					if($projectile !== null){
						$projectile->setMotion($projectile->getMotion()->multiply($this->getThrowForce()));
					}

					if($projectile instanceof Projectile){
						$player->getServer()->getPluginManager()->callEvent($projectileEv = new ProjectileLaunchEvent($projectile));
						if($projectileEv->isCancelled()){
							$projectile->flagForDespawn();
						}else{
							$projectile->spawnToAll();
							$player->getLevel()->addSound(new LaunchSound($player), $player->getViewers());
						}
					}

					//Todo: Wait weather support
					// $weather = Fishing::$weatherData[$player->getLevel()->getId()];
					// if(($weather->isRainy() || $weather->isRainyThunder())){
						// $rand = mt_rand(15, 50);
					// }else{
						$rand = mt_rand(30, 100);
					// }
					if($this->hasEnchantments()){
						foreach(Utils::getEnchantments($this) as $enchantment){
							switch($enchantment->getId()){
								case Enchantment::LURE:
									$divisor = $enchantment->getLevel() * 0.50;
									$rand = intval(round($rand / $divisor)) + 3;
									break;
							}
						}
					}

					$projectile->attractTimer = $rand * 20;

					$session->fishingHook = $projectile;
					$session->fishing = true;
				}else{
					$projectile = $session->fishingHook;
					if($projectile instanceof FishingHook){
						$session->unsetFishing();

						if($player->getLevel()->getBlock($projectile->asVector3())->getId() == Block::WATER || $player->getLevel()->getBlock($projectile)->getId() == Block::WATER){
							$damage = 5;
						}else{
							$damage = mt_rand(10, 15); // TODO: Implement entity / block collision properly
						}

						$this->applyDamage($damage);

						if($projectile->coughtTimer > 0){
							//$weather = Fishing::$weatherData[$player->getLevel()->getId()];
							$lvl = 0;
							if($this->hasEnchantments()){
								if($this->hasEnchantment(Enchantment::LUCK_OF_THE_SEA)){
									$lvl = $this->getEnchantment(Enchantment::LUCK_OF_THE_SEA)->getLevel();
								}
							}
						//	if(($weather->isRainy() || $weather->isRainyThunder()) && $lvl == 0){
						//		$lvl = 2;
						//	}else{
						//		$lvl = 0;
						//	}
							$item = FishingLootTable::getRandom($lvl);
							$player->getInventory()->addItem($item);

							$player->addXp(mt_rand(1, 6));
						}
					}
				}
			}

		return true;
	}

	public function getProjectileEntityType(): string{
		return "FishingHook";
	}

	public function getThrowForce(): float{
		return 1.6;
	}
}