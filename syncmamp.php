<?php
class SyncMAMP

{
	public static function close($project)
	{

		$config = json_decode(file_get_contents('config.json'));

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
			shell_exec("\"" . (isset($config->mysql->mysql) ? ($config->mysql->mysql) : ("mysql")) . "\" -h " . $config->mysql->host . " --port " . $config->mysql->port . " -u " . $config->mysql->username . " -p\"" . $config->mysql->password . "\" -e \"drop database if exists `" . $project . "`; create database `" . $project . "`;\"");
			shell_exec("\"" . (isset($config->mysql->mysql) ? ($config->mysql->mysql) : ("mysql")) . "\" -h " . $config->mysql->host . " --port " . $config->mysql->port . " -u " . $config->mysql->username . " -p\"" . $config->mysql->password . "\" " . $project . " --default-character-set=utf8 < \"" . $config->target . "/" . $project . ".mysql" . "\"");
			unlink($config->target . "/" . $project . ".mysql");
		}

		if (file_exists($config->target . "/" . $project . ".pgsql"))
		{
			shell_exec("SET PGPASSWORD=" . $config->pgsql->password . "&& \"" . (isset($config->pgsql->psql) ? ($config->pgsql->psql) : ("psql")) . "\" -U " . $config->pgsql->username . " -d " . $project . " -c \"drop schema public cascade; create schema public;\"");
			shell_exec("SET PGPASSWORD=" . $config->pgsql->password . "&& \"" . (isset($config->pgsql->psql) ? ($config->pgsql->psql) : ("psql")) . "\" -U " . $config->pgsql->username . " -d " . $project . " -1 -f \"" . $config->target . "/" . $project . ".pgsql\"");
			unlink($config->target . "/" . $project . ".pgsql");
		}

		unlink($config->target . "/" . $project . ".zip");
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