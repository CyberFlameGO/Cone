<?php
require __DIR__."/Cone.class.php";
require __DIR__."/Package.class.php";
require __DIR__."/UnixPackageManager.class.php";
use hellsh\Cone\Cone;
use hellsh\Cone\Package;
use hellsh\Cone\UnixPackageManager;
switch(@$argv[1])
{
	case "info":
	case "about":
	case "version":
	echo "Cone v".Cone::VERSION." using package list rev. ".Cone::getPackageListRevision().".\nUse 'cone update' to check for updates.\n";
	break;

	case "list":
	case "packages":
	case "installed":
	$installed_packages = Cone::getInstalledPackagesList();
	if(empty($installed_packages))
	{
		die("0 packages installed.\n");
	}
	echo count($installed_packages)." package".(count($installed_packages) == 1 ? "" : "s")." installed; ";
	$packages = [];
	$dependencies = [];
	foreach($installed_packages as $name => $data)
	{
		if($data["manual"])
		{
			$packages[$name] = $data;
		}
		else
		{
			$dependencies[$name] = $data;
		}
	}
	echo count($packages)." manually-installed:\n";
	foreach($packages as $name => $data)
	{
		echo $name;
		if(array_key_exists("version", $data))
		{
			echo " v".$data["version"];
		}
		echo "\n";
	}
	if(empty($dependencies))
	{
		die("and 0 dependencies.\n");
	}
	echo "and ".count($dependencies)." dependenc".(count($dependencies) == 1 ? "y" : "ies").":\n";
	foreach($dependencies as $name => $data)
	{
		echo $name;
		if(array_key_exists("version", $data))
		{
			echo " v".$data["version"];
		}
		echo "\n";
	}
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
		if(array_key_exists($package->name, $installed_packages))
		{
			if($installed_packages[$package->name]["manual"])
			{
				echo $package->name." is already installed.\n";
			}
			else
			{
				echo $package->name." is already installed; now set to manually installed.\n";
				$installed_packages[$package->name]["manual"] = true;
			}
			continue;
		}
		array_push($packages, $package);
	}
	$before = count($installed_packages);
	$env_flag = false;
	foreach($packages as $package)
	{
		try
		{
			$package->install($installed_packages, $env_flag);
		}
		catch(Exception $e)
		{
			echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
		}
	}
	$count = (count($installed_packages) - $before);
	echo "Installed ".$count." package".($count == 1 ? "" : "s").".\n";
	if($env_flag)
	{
		echo "You might need to open a new terminal window to use installed packages, as environment variables were added.\n";
	}
	break;

	case "update":
	case "upgrade":
	if(!Cone::isAdmin())
	{
		die("Cone needs to run as administrator/root to update.\n");
	}
	$remote_versions = json_decode(file_get_contents("https://getcone.org/versions.json"), true);
	if($remote_versions["cone"] != Cone::VERSION)
	{
		echo "Cone v".$remote_versions["cone"]." is available.\n";
		file_put_contents(__DIR__."/../_update_", "");
		exit;
	}
	echo "Cone is up-to-date.\n";
	if($remote_versions["packages"] > Cone::getPackageListRevision())
	{
		echo "Updating package list rev. ".Cone::getPackageListRevision()." to rev. ".$remote_versions["packages"]."...";
		file_put_contents(Cone::PACKAGES_FILE, file_get_contents("https://getcone.org/packages.json"));
		echo " Done.\n";
	}
	else
	{
		echo "Package list is up-to-date.\n";
	}
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
	UnixPackageManager::updateAllPackages();
	break;

	case "force-self-update":
	if(!Cone::isAdmin())
	{
		die("Cone needs to run as administrator/root to update.\n");
	}
	file_put_contents(__DIR__."/../_update_", "");
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
			if($p != null)
			{
				$name = $p->name;
			}
		}
		if(!array_key_exists($name, $installed_packages))
		{
			echo $name." is not installed.\n";
			continue;
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
				die($name." depends on ".$package.".\n");
			}
		}
	}
	$before = count($installed_packages);
	foreach($packages as $package)
	{
		echo "Removing ".$package."...\n";
		try
		{
			(new Package($package))->uninstall();
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
