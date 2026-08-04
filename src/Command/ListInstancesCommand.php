<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;
use Learnomancer;

class ListInstancesCommand extends BaseCommand
{
	/**
	 * Returns the description of the command
	 */
	public static function getDescription(): string
	{
		return 'List currently running instances for an existing class';
	}

	/**
	 * Configures arguments, and options for the command
	 */
	protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		$parser->addArguments([
			'slug' => [
				'required' => false,
				'help' => 'The slug/suffix for the class',
			],
			'region' => [
				'required' => false,
				'help' => 'The region to list from',
			],
		]);

		$parser->removeOption('verbose')->removeOption('quiet');

		return $parser;
	}

	/**
	 * Executes the command
	 */
	public function execute(Arguments $args, ConsoleIo $io): ?int
	{
		// Get arguments
		$slug   = $args->getArgument('slug');
		$region = $args->getArgument('region');

		if (!$slug || !$region)
		{
			$discovered = Learnomancer\LearnomancerConfig::discoverActiveConfig();
			$io->err('<error>Error: Missing required argument(s).</error>');
			$io->err("Usage: learnomancer list-instances <slug> <region>\n");

			if (!empty($discovered['slugs']))
			{
				$io->out('Discovered active slugs in your workspace: ' . implode(', ', $discovered['slugs']));
			}
			else
			{
				$io->out('No active class configurations discovered in your workspace.');
			}

			if (!empty($discovered['regions']))
			{
				$io->out('Discovered active regions in your workspace: ' . implode(', ', $discovered['regions']));
			}
			else
			{
				$io->out('Standard AWS regions: us-east-1, us-west-1, us-west-2, eu-west-1, etc.');
			}

			if (!empty($discovered['slugs']) && !empty($discovered['regions']))
			{
				$sampleSlug   = $discovered['slugs'][0];
				$sampleRegion = $discovered['regions'][0];
				$io->out("\nSuggested command to run:");
				$io->out("  bin/learnomancer list-instances {$sampleSlug} {$sampleRegion}");
			}
			else
			{
				$io->out("\nExample command to run:");
				$io->out('  bin/learnomancer list-instances ACME us-west-1');
			}

			return static::CODE_ERROR;
		}

		/** @var \Learnomancer\Command\Helper\TableHelper $tableHelper */
		$tableHelper = $io->helper('Learnomancer\Command\Helper\TableHelper');

		try
		{
			$ec2       = new Learnomancer\AwsClient($region);
			$instances = $ec2->getInstancesBySlug($slug);

			if (empty($instances))
			{
				$io->warning("! No instances found for '{$slug}' in '{$region}'\n");

				return static::CODE_SUCCESS;
			}

			$tableData = [['InstanceId', 'Hostname', 'PublicIpAddress']];

			foreach ($instances as $instance)
			{
				$tableData[] = [$instance['InstanceId'], $instance['Hostname'], $instance['PublicIpAddress']];
			}

			$io->out("========== Listing instances for '{$slug}' in '{$region}' ==========");
			$tableHelper->output($tableData);
		}
		catch (Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error("** Unable to list instances: {$e->getMessage()}**");

			return static::CODE_ERROR;
		}

		return static::CODE_SUCCESS;
	}
}
