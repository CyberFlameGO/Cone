<?php
require __DIR__."/Cone.class.php";
require __DIR__."/ConePackage.class.php";
use hellsh\Cone;
switch(@$argv[1])
{
	case "info":
	case "about":
	case "version":
	echo "Cone v".Cone::VERSION."\nPackage list v".Cone::getPackagesVersion()["major"].".".Cone::getPackagesVersion()["revision"]."\nUse 'cone update' to check for updates.\n";
	break;

	case "list":
	case "packages":
	case "installed":
	$installed_packages = Cone::getInstalledPackagesList();
	if(empty($installed_packages))
	{
		echo "0 packages installed.\n";
	}
	else
	{
		echo count($installed_packages)." packages installed:\n";
		foreach($installed_packages as $name => $data)
		{
			echo "- ".$name."\n";
		}
	}
	break;

	case "i":
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
	$installed_packages = Cone::getInstalledPackagesList();
	$packages = [];
	for($i = 2; $i < count($argv); $i++)
	{
		$name = strtolower($argv[$i]);
		$package = Cone::getPackage($name);
		if($package === NULL)
		{
			die("Unknown package: ".$name."\n");
		}
		if(array_key_exists($name, $installed_packages))
		{
			if(!$installed_packages[$name]["manual"])
			{
				echo $name." is already installed; now set to manually installed.\n";
				$installed_packages[$name]["manual"] = true;
			}
			else
			{
				echo $name." is already installed.\n";
			}
			continue;
		}
		array_push($packages, $package);
	}
	$before = count($installed_packages);
	foreach($packages as $package)
	{
		$package->install($installed_packages);
	}
	echo "Installed ".(count($installed_packages) - $before)." packages.\n";
	Cone::setInstalledPackagesList($installed_packages);
	break;

	case "update":
	case "upgrade":
	if(!Cone::isAdmin())
	{
		die("Cone needs to run as administrator/root to update.\n");
	}
	$remote_versions = json_decode(file_get_contents("https://cone.hell.sh/versions.json"), true);
	if($remote_versions["cone"] != Cone::VERSION)
	{
		echo "Cone v".$remote_versions["cone"]." is available.\nFollow the instructions at https://cone.hell.sh/#installation to update.\n";
	}
	else
	{
		echo "Cone is up-to-date.\n";
	}
	if($remote_versions["packages"]["major"] > Cone::PACKAGES_MAJOR)
	{
		echo "A Cone update is required to update package list.\n";
	}
	else if($remote_versions["packages"]["revision"] > Cone::getPackagesVersion()["revision"] || $remote_versions["packages"]["major"] > Cone::getPackagesVersion()["major"])
	{
		echo "Updating package list v".Cone::getPackagesVersion()["major"].".".Cone::getPackagesVersion()["revision"]." to v".$remote_versions["packages"]["major"].$remote_versions["packages"]["revision"]."...";
		file_put_contents(Cone::PACKAGES_FILE, file_get_contents("https://cone.hell.sh/packages.json"));
		echo " Done.\n";
	}
	else
	{
		echo "Package list is up-to-date.\n";
	}
	foreach(Cone::getInstalledPackagesList() as $package)
	{
		Cone::getPackage($package)->update();
	}
	if(!Cone::isWindows())
	{
		shell_exec("if [ \"\$(which aptitude)\" != \"\" ]; then\naptitude update\naptitude -y upgrade\nelif [ \"\$(which apt-get)\" != \"\" ]; then\napt-get update\napt-get -y upgrade\nelif [ \"\$(which pacman)\" != \"\" ]; then\npacman --noconfirm -Syu\nfi");
	}
	break;

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
		$package = Cone::getPackage($name);
		if($package === NULL)
		{
			die("Unknown package: ".$name."\n");
		}
		if(!array_key_exists($name, $installed_packages))
		{
			echo $name." is not installed.\n";
			continue;
		}
		array_push($packages, $package);
	}
	foreach($packages as $package)
	{
		foreach($installed_packages as $name => $data)
		{
			if(!in_array($name, $packages) && in_array($package->name, Cone::getPackage($name)->getDependenciesList()))
			{
				die($name." depends on ".$package->name.".\n");
			}
		}
	}
	$before = count($installed_packages);
	foreach($packages as $package)
	{
		echo "Removing ".$package->name."...\n";
		$package->uninstall();
		unset($installed_packages[$package->name]);
	}
	foreach($installed_packages as $name => $data)
	{
		$package = Cone::getPackage($name);
		if(!$package->isManuallyInstalled())
		{
			$needed = false;
			foreach($installed_packages as $name_ => $data_)
			{
				if(in_array($name, Cone::getPackage($name_)->getDependenciesList()))
				{
					$needed = true;
					break;
				}
			}
			if(!$needed)
			{
				echo "Removing now-unneeded dependency ".$name."...\n";
				$package->uninstall();
				unset($installed_packages[$name]);
			}
		}
	}
	echo "Removed ".($before - count($installed_packages))." packages.\n";
	Cone::setInstalledPackagesList($installed_packages);
	break;

	default:
	echo "Syntax: cone [info|list|get <packages ...>|update|remove <packages ...>]\n";
}
