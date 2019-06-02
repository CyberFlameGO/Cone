<?php
chdir(realpath(__DIR__."/.."));
require "src/Cone.class.php";
require "src/Package.class.php";
require "src/UnixPackageManager.class.php";
use Cone\Cone;
use Cone\Package;
use Cone\UnixPackageManager;
switch(@$argv[1])
{
	case "info":
	case "about":
	case "version":
	echo "Cone v".Cone::VERSION."\nUse 'cone update' to check for updates.\n";
	break;

	case "list":
	case "packages":
	case "installed":
	Cone::printInstalledPackagesList();
	break;

	case "i":
	case "git":
	case "get":
	case "instal":
	case "install":
	if(!Cone::isAdmin())
	{
		die("Cone needs to run as administrator/root to install packages.\n");
	}
	if(empty($argv[2]))
	{
		die("Syntax: cone install <packages ...>\n");
	}
	if(in_array($argv[2], ["gud", "good"]))
	{
		die("I'm afraid there's no easy way for that.\n");
	}
	$installed_packages = Cone::getInstalledPackagesList();
	$packages = [];
	for($i = 2; $i < count($argv); $i++)
	{
		$name = strtolower($argv[$i]);
		$package = Cone::getPackage($name, true);
		if($package === NULL)
		{
			die("Unknown package: ".$name."\n");
		}
		if(array_key_exists($package->getName(), $installed_packages))
		{
			if($installed_packages[$package->getName()]["manual"])
			{
				echo $package->getDisplayName()." is already installed.\n";
			}
			else
			{
				echo $package->getDisplayName()." is already installed; now set to manually installed.\n";
				$installed_packages[$package->getName()]["manual"] = true;
			}
			continue;
		}
		array_push($packages, $package);
	}
	$before = count($installed_packages);
	$env_arr = [];
	foreach($packages as $package)
	{
		try
		{
			$package->install($installed_packages, $env_arr);
		}
		catch(Exception $e)
		{
			echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
		}
	}
	$count = (count($installed_packages) - $before);
	echo "Installed ".$count." package".($count == 1 ? "" : "s").".\n";
	if($env_arr)
	{
		echo "In order to use the environment variables that were just defined (".join(", ", $env_arr)."), open a new terminal window.\n";
	}
	break;

	case "update":
	case "upgrade":
	if(!Cone::isAdmin())
	{
		die("Cone needs to run as administrator/root to update.\n");
	}
	if(@$argv[2] == "--post-install")
	{
		echo "Downloading package list...";
	}
	else
	{
		$remote_version = trim(file_get_contents("https://code.getcone.org/version.txt"));
		if(version_compare($remote_version, Cone::VERSION, ">"))
		{
			echo "Cone v".$remote_version." is available.\n";
			file_put_contents("_update_", "");
			exit;
		}
		echo "Cone is up-to-date.\nUpdating package list...";
	}
	$packages = [];
	foreach(Cone::getRemotePackageLists() as $list)
	{
		$packages = array_merge($packages, json_decode(file_get_contents($list), true));
	}
	file_put_contents(Cone::PACKAGES_FILE, json_encode($packages));
	echo " Done.\n";
	foreach(Cone::getInstalledPackagesList() as $name => $data)
	{
		try
		{
			Cone::getPackage($name)->update();
		}
		catch(Exception $e)
		{
			echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
		}
	}
	Cone::removeUnneededDependencies();
	/** @noinspection PhpUnhandledExceptionInspection */
	UnixPackageManager::updateAllPackages();
	break;

	case "force-self-update":
	if(!Cone::isAdmin())
	{
		die("Cone needs to run as administrator/root to update.\n");
	}
	file_put_contents("_update_", "");
	break;

	case "rm":
	case "delete":
	case "remove":
	case "uninstal":
	case "uninstall":
	if(!Cone::isAdmin())
	{
		die("Cone needs to run as administrator/root to uninstall packages.\n");
	}
	$installed_packages = Cone::getInstalledPackagesList();
	$packages = [];
	for($i = 2; $i < count($argv); $i++)
	{
		$name = strtolower($argv[$i]);
		if(!array_key_exists($name, $installed_packages))
		{
			$p = Cone::getPackage($name, true);
			if($p == null)
			{
				echo $name." is not installed.\n";
				continue;
			}
			$name = $p->getName();
		}
		array_push($packages, $name);
	}
	foreach($packages as $package)
	{
		foreach($installed_packages as $name => $data)
		{
			if(in_array($name, $packages))
			{
				continue;
			}
			$p = Cone::getPackage($name);
			if($p != null && in_array($package, $p->getDependenciesList()))
			{
				die($p->getDisplayName()." depends on ".$package.".\n");
			}
		}
	}
	$before = count($installed_packages);
	foreach($packages as $package)
	{
		echo "Removing ".$package."...\n";
		try
		{
			(new Package(["name" => $package]))->uninstall();
			unset($installed_packages[$package]);
		}
		catch(Exception $e)
		{
			echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
		}
	}
	Cone::removeUnneededDependencies($installed_packages);
	$count = ($before - count($installed_packages));
	echo "Removed ".$count." package".($count == 1 ? "" : "s").".\n";
	Cone::setInstalledPackagesList($installed_packages);
	break;

	default:
	echo "Syntax: cone [info|list|update|get <packages ...>|remove <packages ...>]\n";
}
