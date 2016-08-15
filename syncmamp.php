<?php
class SyncMAMP

{
	public static function backup($project)
	{
		$config = json_decode(file_get_contents('config.json'));
		if (!is_dir($config->source . "/" . $project))
		{
			die('folder missing');
		}

		if (file_exists($config->target . "/" . $project . ".zip"))
		{
			unlink($config->target . "/" . $project . ".zip");
		}

		chdir($config->source . "/" . $project . "/");
		shell_exec("zip -r " . $config->target . "/" . $project . ".zip .");
		passthru("\"" . (isset($config->mysql->mysqldump) ? ($config->mysql->mysqldump) : ("mysqldump")) . "\" -h " . $config->mysql->host . " --port " . $config->mysql->port . " -u " . $config->mysql->username . " -p\"" . $config->mysql->password . "\" " . $project . " > \"" . $config->target . "/" . $project . ".mysql\"", $return);
		if ($return > 0)
		{
			unlink($config->target . "/" . $project . ".mysql");
		}

		passthru("\"" . (isset($config->pgsql->pg_dump) ? ($config->pgsql->pg_dump) : ("pg_dump")) . "\" --dbname=postgresql://" . $config->pgsql->username . ":" . $config->pgsql->password . "@" . $config->pgsql->host . ":" . $config->pgsql->port . "/" . $project . " > \"" . $config->target . "/" . $project . ".pgsql\"", $return);
		if ($return > 0)
		{
			unlink($config->target . "/" . $project . ".pgsql");
		}
	}

	public static function restore($project)
	{
		$config = json_decode(file_get_contents('config.json'));
		if (!file_exists($config->target . "/" . $project . ".zip"))
		{
			die('missing file');
		}

		if (is_dir($config->source . "/" . $project))
		{
			shell_exec("rm -rf " . $config->source . "/" . $project);
		}

		shell_exec("unzip " . $config->target . "/" . $project . ".zip -d " . $config->source . "/" . $project . "/");
		if (file_exists($config->target . "/" . $project . ".mysql"))
		{
			shell_exec("\"" . (isset($config->mysql->mysql) ? ($config->mysql->mysql) : ("mysql")) . "\" -h " . $config->mysql->host . " --port " . $config->mysql->port . " -u " . $config->mysql->username . " -p\"" . $config->mysql->password . "\" -e \"drop database if exists " . $project . "; create database " . $project . ";\"");
			shell_exec("\"" . (isset($config->mysql->mysql) ? ($config->mysql->mysql) : ("mysql")) . "\" -h " . $config->mysql->host . " --port " . $config->mysql->port . " -u " . $config->mysql->username . " -p\"" . $config->mysql->password . "\" " . $project . " --default-character-set=utf8 < \"" . $config->target . "/" . $project . ".mysql" . "\"");
		}

		if (file_exists($config->target . "/" . $project . ".pgsql"))
		{
			shell_exec("SET PGPASSWORD=" . $config->pgsql->password . "&& \"" . (isset($config->pgsql->psql) ? ($config->pgsql->psql) : ("psql")) . "\" -U " . $config->pgsql->username . " -d " . $project . " -c \"drop schema public cascade; create schema public;\"");
			shell_exec("SET PGPASSWORD=" . $config->pgsql->password . "&& \"" . (isset($config->pgsql->psql) ? ($config->pgsql->psql) : ("psql")) . "\" -U " . $config->pgsql->username . " -d " . $project . " -1 -f \"" . $config->target . "/" . $project . ".pgsql\"");
		}
	}
}

// usage from command line

if (!isset($argv) || empty($argv) || !isset($argv[1]) || !isset($argv[2]))
{
	die('missing options');
}

if ($argv[1] != "backup" && $argv[1] != "restore")
{
	die('wrong action');
}

if ($argv[1] == "backup")
{
	SyncMAMP::backup($argv[2]);
}

if ($argv[1] == "restore")
{
	SyncMAMP::restore($argv[2]);
}

