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
	case "-version":
	case "--version":
		echo "Cone ".Cone::VERSION." running on PHP ".PHP_VERSION.".\nUse 'cone update' to check for updates.\n";
		break;
	case "ls":
	case "list":
	case "installed":
	case "list-installed":
		Cone::printInstalledPackagesList();
		echo "Use 'cone installable' for a list of installable packages.\n";
		break;
	case "installable":
	case "list-installable":
		echo "You can 'cone get' these packages:\n";
		foreach(Cone::getPackages() as $package)
		{
			echo $package->getName().": ".$package->getDisplayName();
			if($package->hasVersion())
			{
				echo " ";
				$version = $package->getVersion();
				if(strpos($version, "dev") === false)
				{
					echo "v";
				}
				echo $version;
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
			die("Cone needs to run as ".Cone::rootOrAdmin()." to install packages.\n");
		}
		if(empty($argv[2]))
		{
			die("Syntax: cone install <packages ...>\n");
		}
		if(in_array($argv[2], [
			"gud",
			"good"
		]))
		{
			die("I'm afraid there's no easy way for that.\n");
		}
		$installed_packages = Cone::getInstalledPackagesList();
		$packages = [];
		$force = false;
		for($i = 2; $i < count($argv); $i++)
		{
			if(in_array(strtolower($argv[$i]), [
				'--force',
				'-f'
			]))
			{
				if($force)
				{
					echo "Double-force activated!\n";
				}
				$force = true;
				continue;
			}
		}
		for($i = 2; $i < count($argv); $i++)
		{
			$name = strtolower($argv[$i]);
			if($force && in_array($name, [
					'--force',
					'-f'
				]))
			{
				continue;
			}
			$package = Cone::getPackage($name, true);
			if($package === null)
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
			if(in_array($name, $package->getRiskyAliases()))
			{
				echo "When you say ".$name.", do you mean ".$package->getDisplayName()."?";
				if($force)
				{
					echo " [Y/n]\n";
				}
				else if(!Cone::yesOrNo())
				{
					continue;
				}
			}
			array_push($packages, $package);
		}
		$before = count($installed_packages);
		$env_arr = [];
		foreach($packages as $package)
		{
			try
			{
				$package->install($installed_packages, $force, $env_arr);
			}
			catch(Exception $e)
			{
				echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
			}
		}
		$count = (count($installed_packages) - $before);
		echo "Installed ".$count." package".($count == 1 ? "" : "s").".\n";
		Cone::setInstalledPackagesList($installed_packages);
		if($env_arr)
		{
			echo "In order to use the environment variable".(count($env_arr) == 1 ? " that was" : "s that were")." just defined (".join(", ", $env_arr)."), open a new terminal window.\n";
		}
		break;
	case "up":
	case "update":
	case "upgrade":
		if(!Cone::isAdmin())
		{
			die("Cone needs to run as ".Cone::rootOrAdmin()." to update.\n");
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
		$_packages = Cone::getPackages();
		foreach(Cone::getRemotePackageLists() as $list)
		{
			$packages = array_merge($packages, json_decode(file_get_contents($list), true));
		}
		file_put_contents(Cone::PACKAGES_FILE, json_encode($packages));
		echo " Done.\n";
		$installed_packages = Cone::getInstalledPackagesList();
		foreach($installed_packages as $name => $data)
		{
			try
			{
				Cone::getPackage($name)
					->update($installed_packages);
			}
			catch(Exception $e)
			{
				echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
			}
		}
		Cone::removeUnneededDependencies($installed_packages);
		Cone::setInstalledPackagesList($installed_packages);
		/** @noinspection PhpUnhandledExceptionInspection */
		if($native = UnixPackageManager::getNativePackageManager())
		{
			echo "Would you like to perform an update with {$native} as well?";
			if(Cone::yesOrNo())
			{
				/** @noinspection PhpUnhandledExceptionInspection */
				UnixPackageManager::updateAllPackages();
			}
		}
		break;
	case "force-self-update":
		if(!Cone::isAdmin())
		{
			die("Cone needs to run as ".Cone::rootOrAdmin()." to update.\n");
		}
		echo "Do you know what you're doing?";
		if(!Cone::noOrYes())
		{
			die("Aborting.\n");
		}
		file_put_contents("_update_", "");
		break;
	case "rm":
	case "del":
	case "delete":
	case "remove":
	case "uninstal":
	case "uninstall":
		if(!Cone::isAdmin())
		{
			die("Cone needs to run as ".Cone::rootOrAdmin()." to uninstall packages.\n");
		}
		$installed_packages = Cone::getInstalledPackagesList();
		$packages = [];
		for($i = 2; $i < count($argv); $i++)
		{
			$name = strtolower($argv[$i]);
			if($name == "cone")
			{
				die("If you're looking to uninstall Cone, use `cone self-uninstall`.\n");
			}
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
				(new Package(["name" => $package]))->uninstall($installed_packages);
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
	case "self-uninstall":
		if(!Cone::isAdmin())
		{
			die("Cone needs to run as ".Cone::rootOrAdmin()." to self-uninstall.\n");
		}
		$installed_packages = Cone::getInstalledPackagesList();
		if(count($installed_packages) > 0)
		{
			echo "You currently have ";
			Cone::printInstalledPackagesList($installed_packages);
			echo "Are you sure you want to remove them and Cone?";
			if(!Cone::noOrYes())
			{
				die("Aborting.\n");
			}
			Cone::timeToContemplate();
			foreach($installed_packages as $name => $data)
			{
				echo "Removing ".$name."...\n";
				try
				{
					(new Package(["name" => $name]))->uninstall();
				}
				catch(Exception $e)
				{
					echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
				}
			}
		}
		else
		{
			echo "Are you sure you want to remove Cone from your system?";
			if(!Cone::noOrYes())
			{
				die("Aborting.\n");
			}
			Cone::timeToContemplate();
		}
		if(Cone::isWindows())
		{
			shell_exec('REG ADD "HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /F /V PATH /T REG_SZ /D "'.str_replace(realpath("path")."\\;", "", getenv("PATH")).'"');
		}
		file_put_contents("_uninstall_", "");
		break;
	default:
		echo "Syntax: cone [info|update|get ... [--force]|list|installable|remove ...|self-uninstall]\n";
}
