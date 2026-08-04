<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;
use Learnomancer;

class SshConfigCommand extends BaseCommand
{
	/**
	 * Returns the description of the command
	 */
	public static function getDescription(): string
	{
		return 'Generate an SSH config file for an existing class';
	}

	/**
	 * Configures arguments, and options for the command
	 */
	protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		$parser->setDescription([
			'This command will generate an SSH config containing connection information to each of the instances.',
			'You can redirect the output to a file, or use the -o flag to save to a file.',
		]);

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

		$parser->addOption('output', [
			'short' => 'o',
			'help' => 'Save output to file',
			'boolean' => true,
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
			$io->err("Usage: learnomancer ssh-config <slug> <region> [--output]\n");

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
				$io->out("  bin/learnomancer ssh-config {$sampleSlug} {$sampleRegion}");
			}
			else
			{
				$io->out("\nExample command to run:");
				$io->out('  bin/learnomancer ssh-config ACME us-west-1');
			}

			return static::CODE_ERROR;
		}

		try
		{
			$ec2          = new Learnomancer\AwsClient($region);
			$reservations = $ec2->getInstancesBySlug($slug, stateFilters: ['pending', 'running']);

			if (empty($reservations))
			{
				$io->warning("!! No instances found for '{$slug}' in '{$region}' !!\n");

				return static::CODE_SUCCESS;
			}

			$contents    = '';
			$frontLength = strlen("Percona-Training-{$slug}-");

			foreach ($reservations as $instance)
			{
				$typeTeamName = substr($instance['Hostname'], $frontLength);

				$contents .= "Host {$instance['Hostname']} {$typeTeamName}\n";
				$contents .= "  HostName {$instance['PublicIpAddress']}\n";
				$contents .= "  User rocky\n";
				$contents .= "  IdentityFile Percona-Training.key\n";
				$contents .= "  StrictHostKeyChecking no\n";
				$contents .= "  ForwardAgent yes\n";
			}

			// Output
			if ($args->getOption('output'))
			{
				$filename = "ssh_{$slug}";
				$io->createFile($filename, $contents, forceOverwrite: true);
			} else {
				$io->out($contents);
			}
		}
		catch(Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error("** Unable to create VPC: {$e->getMessage()}**");

			return static::CODE_ERROR;
		}

		return static::CODE_SUCCESS;
	}
}
