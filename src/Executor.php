<?php declare(strict_types=1);

/*
  Copyright (c) 2023, Manticore Software LTD (https://manticoresearch.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License version 2 or any later
  version. You should have received a copy of the GPL license along with this
  program; if you did not, you can find it at http://www.gnu.org/
*/

namespace Manticoresearch\Buddy\Plugin\Backup;

use Manticoresearch\Backup\Lib\FileStorage;
use Manticoresearch\Backup\Lib\ManticoreBackup;
use Manticoresearch\Backup\Lib\ManticoreClient;
use Manticoresearch\Backup\Lib\ManticoreConfig;
use Manticoresearch\Buddy\Core\Plugin\Executor as BaseExecutor;
use Manticoresearch\Buddy\Core\Task\Task;
use Manticoresearch\Buddy\Core\Task\TaskResult;
use parallel\Runtime;

/**
 * This is the class to handle BACKUP ... SQL command
 */
class Executor extends BaseExecutor {
  /**
   *  Initialize the executor
   *
   * @param Request $request
   * @return void
   */
	public function __construct(protected Request $request) {
	}

  /**
   * Process the request and return self for chaining
   *
	 * @param Runtime $runtime
   * @return Task
   */
	public function run(Runtime $runtime): Task {
		// We run in a thread anyway but in case if we need blocking
		// We just waiting for a thread to be done
		$isAsync = $this->request->options['async'] ?? false;
		$method = $isAsync ? 'deferInRuntime' : 'createInRuntime';

		$task = Task::$method(
			$runtime,
			static function (Request $request): TaskResult {
				$config = new ManticoreConfig($request->configPath);
				$client = new ManticoreClient($config);
				$storage = new FileStorage(
					$request->path,
					$request->options['compress'] ?? false
				);
				ManticoreBackup::run('store', [$client, $storage, $request->tables]);
				// TODO: make standard response interface
				return new TaskResult(
					[[
						'total' => 1,
						'error' => '',
						'warning' => '',
						'columns' => [
							[
								'Path' => [
									'type' => 'string',
								],
							],
						],
						'data' => [
							[
								'Path' => $storage->getBackupPaths()['root'],
							],
						],
					],
					]
				);
			},
			[$this->request]
		);

		return $task->run();
	}

	/**
	 * @return array<string>
	 */
	public function getProps(): array {
		return [];
	}
}
