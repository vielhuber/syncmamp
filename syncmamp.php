<?php
class SyncMAMP

{
	public static function close($project)
	{

		$config = json_decode(file_get_contents('config.json'));

		if ($project !== null && $project != "" && $project == "all") {

			// delete all empty folders
			$directories = glob($config->source . '/*' , GLOB_ONLYDIR);
			foreach($directories as $dir)
			{
				$count = 0;
				self::countFiles($dir, $count);
				if($count === 0)
				{
					chdir($config->source);
					shell_exec("rm -rf " . $dir);
				}
			}
			chdir(realpath(dirname(__FILE__)));

			// for all others: close em
			$directories = glob($config->source . '/*' , GLOB_ONLYDIR);
			foreach($directories as $dir)
			{
				$project = substr($dir, strrpos($dir,"/")+1);
				self::close($project);
			}
			
			return true;
		}

		if ($project === null || $project == "" || !is_dir($config->source . "/" . $project))
		{
			die('folder missing');
		}

		if (file_exists($config->target . "/" . $project . ".zip"))
		{
			unlink($config->target . "/" . $project . ".zip");
		}

		chdir($config->source . "/" . $project . "/");
		shell_exec("zip -0 -r \"" . $config->target . "/" . $project . ".zip\" .");
		passthru("\"" . (isset($config->mysql->mysqldump) ? ($config->mysql->mysqldump) : ("mysqldump")) . "\" -h " . $config->mysql->host . " --port " . $config->mysql->port . " -u " . $config->mysql->username . " -p\"" . $config->mysql->password . "\" " . $project . " 2> \"".$config->source."/log.txt\" > \"" . $config->target . "/" . $project . ".mysql\"", $return);
		if (strpos(file_get_contents($config->source."/log.txt"),"error") !== false)
		{
			unlink($config->target . "/" . $project . ".mysql");
		}
		unlink($config->source."/log.txt");

		passthru("\"" . (isset($config->pgsql->pg_dump) ? ($config->pgsql->pg_dump) : ("pg_dump")) . "\" --dbname=postgresql://" . $config->pgsql->username . ":" . $config->pgsql->password . "@" . $config->pgsql->host . ":" . $config->pgsql->port . "/" . $project . " 2> \"".$config->source."/log.txt\" > \"" . $config->target . "/" . $project . ".pgsql\"", $return);
		if (strpos(file_get_contents($config->source."/log.txt"),"FATAL") !== false)
		{
			unlink($config->target . "/" . $project . ".pgsql");
		}
		unlink($config->source."/log.txt");

		chdir($config->source);
		shell_exec("rm -rf " . $config->source . "/" . $project);
		chdir(realpath(dirname(__FILE__)));

		return true;

	}

	public static function open($project)
	{
		$config = json_decode(file_get_contents('config.json'));
		if ($project === null || $project == "" || !file_exists($config->target . "/" . $project . ".zip"))
		{
			die('missing file');
		}

		if (is_dir($config->source . "/" . $project))
		{
			shell_exec("rm -rf " . $config->source . "/" . $project);
		}

		mkdir( $config->source . "/" . $project );

		shell_exec("unzip \"" . $config->target . "/" . $project . ".zip\" -d " . $config->source . "/" . $project . "/");
		if (file_exists($config->target . "/" . $project . ".mysql"))
		{
			shell_exec("\"" . (isset($config->mysql->mysql) ? ($config->mysql->mysql) : ("mysql")) . "\" -h " . $config->mysql->host . " --port " . $config->mysql->port . " -u " . $config->mysql->username . " -p\"" . $config->mysql->password . "\" -e \"drop database if exists `" . $project . "`; create database `" . $project . "`;\" >nul 2>&1");
			shell_exec("\"" . (isset($config->mysql->mysql) ? ($config->mysql->mysql) : ("mysql")) . "\" -h " . $config->mysql->host . " --port " . $config->mysql->port . " -u " . $config->mysql->username . " -p\"" . $config->mysql->password . "\" " . $project . " --default-character-set=utf8 < \"" . $config->target . "/" . $project . ".mysql" . "\" >nul 2>&1");
			unlink($config->target . "/" . $project . ".mysql");
		}

		if (file_exists($config->target . "/" . $project . ".pgsql"))
		{
			shell_exec("SET PGPASSWORD=" . $config->pgsql->password . "&& \"" . (isset($config->pgsql->psql) ? ($config->pgsql->psql) : ("psql")) . "\" -U " . $config->pgsql->username . " -d " . $project . " -c \"drop schema public cascade; create schema public;\" >nul 2>&1");
			shell_exec("SET PGPASSWORD=" . $config->pgsql->password . "&& \"" . (isset($config->pgsql->psql) ? ($config->pgsql->psql) : ("psql")) . "\" -U " . $config->pgsql->username . " -d " . $project . " -1 -f \"" . $config->target . "/" . $project . ".pgsql\" >nul 2>&1");
			unlink($config->target . "/" . $project . ".pgsql");
		}

		unlink($config->target . "/" . $project . ".zip");

		return true;
	}

	public static function countFiles($dir, &$count)
	{
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (@filetype($dir."/".$object) == "dir") {
						self::countFiles($dir."/".$object, $count); 
					}
					else {
						$count++;
					}
				}
			}
			reset($objects);
		}
	}

}

// usage from command line

if (!isset($argv) || empty($argv) || !isset($argv[1]) || !isset($argv[2]))
{
	die('missing options');
}

if ($argv[1] != "close" && $argv[1] != "open")
{
	die('wrong action');
}

if ($argv[1] == "close")
{
	SyncMAMP::close($argv[2]);
}

if ($argv[1] == "open")
{
	SyncMAMP::open($argv[2]);
}

// example usage
/*
syncmamp open project
syncmamp close project
syncmamp close all
*/