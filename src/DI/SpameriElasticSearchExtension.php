<?php declare(strict_types = 1);

namespace Spameri\Elastic\DI;


class SpameriElasticSearchExtension extends \Nette\DI\CompilerExtension
{

	/**
	 * @var array<mixed>
	 */
	public $defaults = [
		'host' 		=> 'localhost',
		'port' 		=> 9200,
		'debug'		=> FALSE,
		'entities' 	=> [],
	];


	public function loadConfiguration() : void
	{
		parent::loadConfiguration();

		/** @var array $config */
		$config = \Nette\DI\Config\Helpers::merge($this->getConfig(), $this->defaults);

		$this->compiler->getContainerBuilder()->parameters['spameriElasticSearch'] = $config;

		$services = $this->loadFromFile(__DIR__ . '/../Config/Elastic.neon');

		$services = $this->toggleDebugBar($config, $services);

		if ( ! \class_exists(\Symfony\Component\Console\Command\Command::class)) {
			$services = $this->removeCommandDefinitions($services);
		}

		if ( ! \class_exists(\Nette\Security\User::class)) {
			unset($services['services']['userProvider']);
		}

		$this->setConfigOptions($services, $config);

		$this->compiler::loadDefinitions(
			$this->getContainerBuilder(),
			$services['services'],
			$this->name
		);
	}


	public function setConfigOptions(
		array $services,
		array $config
	) : void
	{
		$neonSettingsProvider = $services['services']['neonSettingsProvider']['factory'];
		$neonSettingsProvider->arguments[0] = $config['host'];
		$neonSettingsProvider->arguments[1] = $config['port'];
	}


	public function toggleDebugBar(
		array $config,
		array $services
	) : array
	{
		if ( ! $config['debug']) {
			unset(
				$services['tracy'],
				$services['services']['elasticPanelLogger'],
				$services['services']['nullLogger'],
				$services['services']['elasticPanel'],
				$services['services']['clientBuilder']['setup']
			);

		} else {
			$this->getContainerBuilder()
				->getDefinition('tracy.bar')
				->addSetup('addPanel', ['@' . $this->prefix('elasticPanel')])
			;
		}

		return $services;
	}


	public function removeCommandDefinitions(
		array $services
	): array
	{
		$iterableServices = $services['services'];
		foreach ($iterableServices as $serviceKey => $serviceArray) {
			if (isset($serviceArray['tags'])) {
				foreach ($serviceArray['tags'] as $tag) {
					if ($tag === 'kdyby.console.command') {
						unset($services[$serviceKey]);
					}
				}
			}
		}

		return $services;
	}

}
