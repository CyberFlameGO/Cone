<?php
chdir(realpath(__DIR__));
require "src/Cone.php";
require "src/Package.php";
require "src/UnixPackageManager.php";
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
		Cone::printInstalledPackages();
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
			die("Syntax: cone install <packages ...> [--force]\n");
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
		Cone::setInstalledPackages($installed_packages);
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
			echo "Downloading package list...\n";
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
			echo "Cone is up-to-date.\nUpdating package lists...\n";
		}
		$packages = [];
		$_packages = Cone::getPackages();
		$sources = Cone::getSources();
		$update_sources = false;
		foreach($sources as $url => $name)
		{
			echo "Fetching {$name}... ";
			$res = json_decode(@file_get_contents($url), true);
			if($error = Cone::validateSourceData($res))
			{
				echo $error;
				$local = 0;
				foreach($_packages as $package)
				{
					if($package->getSource() == $url)
					{
						array_push($packages, $package->getData());
						$local++;
					}
				}
				echo " Restored {$local} package".($local == 1 ? "" : "s")." from local cache.\n";
				break;
			}
			foreach($res["packages"] as $package)
			{
				array_push($packages, ["source" => $url] + $package);
			}
			echo "got ".count($res["packages"])." package".(count($res["packages"]) == 1 ? "" : "s").".\n";
			if($name != $res["name"])
			{
				echo $name." is now known as ".$res["name"].".\n";
				$sources[$url] = $res["name"];
				$update_sources = true;
			}
		}
		Cone::setPackages($packages);
		if($update_sources)
		{
			Cone::setSources($sources);
		}
		echo "Updating installed packages...\n";
		$installed_packages = Cone::getInstalledPackagesList();
		foreach($installed_packages as $name => $data)
		{
			try
			{
				$package = Cone::getPackage($name);
				if($package !== null)
				{
					$package->update($installed_packages);
				}
			}
			catch(Exception $e)
			{
				echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
			}
		}
		echo "Finishing up...\n";
		Cone::removeUnneededDependencies($installed_packages);
		Cone::setInstalledPackages($installed_packages);
		if(@$argv[2] != "--post-install" && $native = UnixPackageManager::getNativePackageManager())
		{
			echo "Would you like to perform an update with {$native} as well?";
			if(Cone::yesOrNo())
			{
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
				die("If you're looking to uninstall Cone, use 'cone self-uninstall'.\n");
			}
			$p = Cone::getPackage($name, true);
			if($p === null || !array_key_exists($p->getName(), $installed_packages))
			{
				echo $name." is not installed.\n";
				continue;
			}
			array_push($packages, $p->getName());
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
		Cone::setInstalledPackages($installed_packages);
		break;
	case "sources":
	case "list-sources":
		$sources = Cone::getSources();
		foreach($sources as $url => $name)
		{
			echo "$name\t$url\n";
		}
		break;
	case "add-source":
		if(!Cone::isAdmin())
		{
			die("Cone needs to run as ".Cone::rootOrAdmin()." to manage package sources.\n");
		}
		if(empty($argv[2]))
		{
			die("Syntax: cone add-source <URL>\n");
		}
		$sources = Cone::getSources();
		foreach($sources as $url => $name)
		{
			if(strtolower($url) == strtolower($argv[2]))
			{
				die("That's already one of Cone's package sources.\n");
			}
		}
		echo "Fetching... ";
		$res = @file_get_contents($argv[2]);
		if(!$res)
		{
			die("There doesn't seem to be anything at that URL.\n");
		}
		$res = json_decode($res, true);
		if($res === null)
		{
			die("That file doesn't contain valid JSON.\n");
		}
		if($error = Cone::validateSourceData($res))
		{
			die($error."\n");
		}
		$sources[$argv[2]] = $res["name"];
		Cone::setSources($sources);
		echo "Successfully added ".$res["name"].".\nUse 'cone update' once you're finished managing package sources.\n";
		break;
	case "del-source":
	case "remove-source":
		if(!Cone::isAdmin())
		{
			die("Cone needs to run as ".Cone::rootOrAdmin()." to manage package sources.\n");
		}
		if(empty($argv[2]))
		{
			die("Syntax: cone remove-source <URL>\n");
		}
		$sources = Cone::getSources();
		if(!array_key_exists($argv[2], $sources))
		{
			die("Package source unknown. Keep in mind that you have to use the URL. Use 'cone sources' for a list.\n");
		}
		if($argv[2] == "https://packages.getcone.org/main.json")
		{
			echo "Do you know what you're doing?";
			if(!Cone::noOrYes())
			{
				die("Aborting.\n");
			}
			Cone::timeToContemplate();
		}
		$name = $sources[$argv[2]];
		unset($sources[$argv[2]]);
		Cone::setSources($sources);
		echo "Successfully removed {$name}.\nUse 'cone update' once you're finished managing package sources.\n";
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
			Cone::printInstalledPackages($installed_packages);
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
		}
		echo "Would you also like to remove PHP-CLI?";
		$remove_php = Cone::yesOrNo();
		if(Cone::isWindows())
		{
			$path = str_replace(realpath("path")."\\;", "", getenv("PATH"));
			if($remove_php)
			{
				$phpdir = realpath(dirname(shell_exec("WHERE php.exe")));
				$path = str_replace($phpdir."\\;", "", $path);
				file_put_contents("_uninstall_php_", $phpdir);
			}
			shell_exec('REG ADD "HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /F /V PATH /T REG_SZ /D "'.$path.'"');
		}
		else if($remove_php)
		{
			UnixPackageManager::removePackage("php-cli");
		}
		file_put_contents("_uninstall_", "");
		break;
	default:
		echo /** @lang text */
		<<<EOS
Syntax: cone <command [args]>

These are available Cone commands used in various situations:

get information about Cone and installed packages:
   info               Displays version information
   list               Lists installed packages
   installable        Lists installable packages

manage Cone and installed packages:
   update             Updates Cone and installed packages
   get ... [--force]  Installs the given package(s), optionally forcefully/non-interactively
   remove ...         Uninstalls the given package(s)
   self-uninstall     Removes Cone and installed packages from your system
   force-self-update  Forces an update which can be useful if you've edited Cone's files

manage package sources:
   sources            Lists all sources
   add-source ...     Adds a source by its URL
   remove-source ...  Remove a source by its URL

EOS;
}
