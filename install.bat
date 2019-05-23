@ECHO OFF
TITLE Cone Installer
NET SESSION 1>NUL 2>NUL
IF NOT %errorLevel% == 0 (
	ECHO You need to run this script as administrator.
	PAUSE > NUL
	EXIT
)

CD %ProgramFiles(x86)%
WHERE php.exe > php.txt
IF %errorLevel% == 0 (
	SET /p php=<php.txt
	GOTO phpinstalled
)

CLS
ECHO Downloading PHP...
powershell -Command "Invoke-WebRequest https://windows.php.net/downloads/releases/php-7.3.5-nts-Win32-VC15-x86.zip -UseBasicParsing -OutFile php.zip"

ECHO Unpacking PHP...
powershell -Command "Expand-Archive php.zip -DestinationPath 'PHP 7.3.5'"
ERASE php.zip

ECHO Installing PHP...
SET php=%cd%\PHP 7.3.5\php.exe
SET PATH=%cd%\PHP 7.3.5\;%PATH%

:phpinstalled
DEL php.txt

"%php%" -r "if(php_ini_loaded_file() === false) { $dir = dirname(shell_exec('where php')); file_put_contents($dir.'/php.ini', file_get_contents($dir.'/php.ini-development').\"\nextension_dir=\\\"$dir\\ext\\\"\"); }"
"%php%" -r "file_put_contents(php_ini_loaded_file(), str_replace(';extension=openssl', 'extension=openssl', file_get_contents(php_ini_loaded_file())));"

ECHO Downloading Cone...
CD %ProgramFiles%
IF NOT EXIST Hell.sh\ MKDIR Hell.sh
CD Hell.sh
IF NOT EXIST Cone\ MKDIR Cone
CD Cone
IF EXIST master.zip DEL master.zip
powershell -Command "Invoke-WebRequest https://github.com/hell-sh/Cone/archive/master.zip -UseBasicParsing -OutFile master.zip"

ECHO Unpacking Cone...
powershell -Command "Expand-Archive master.zip -DestinationPath tmp"
ERASE master.zip
IF EXIST src\ RMDIR /S /Q src
MOVE tmp\Cone-master\src src
IF EXIST packages.json DEL packages.json
MOVE tmp\Cone-master\packages.json packages.json
IF EXIST icon.ico DEL icon.ico
MOVE tmp\Cone-master\favicon.ico icon.ico
RMDIR /S /Q tmp

ECHO Registering command...
IF EXIST path\ RMDIR /S /Q path
MKDIR path
ECHO Set oWS = WScript.CreateObject("WScript.Shell") > tmp.vbs
ECHO Set oLink = oWS.CreateShortcut("%cd%\path\cone.lnk") >> tmp.vbs
ECHO oLink.IconLocation = "%cd%\icon.ico" >> tmp.vbs
ECHO oLink.TargetPath = "%php%" >> tmp.vbs
ECHO oLink.Arguments = "src\cli.php" >> tmp.vbs
ECHO oLink.WorkingDirectory = "%cd%" >> tmp.vbs
ECHO oLink.Save >> tmp.vbs
CSCRIPT /nologo tmp.vbs
DEL tmp.vbs
"%php%" -r "exit(strpos(getenv('PATH'), '%cd%\path\;') !== false ? 1 : 0);"
IF %errorLevel% == 0 (
	REG ADD "HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /F /V PATH /T REG_SZ /D "%cd%\path\;%PATH%"
)
"%php%" -r "exit(strpos(getenv('PATHEXT'), ';.LNK') !== false ? 1 : 0);"
IF %errorLevel% == 0 (
	REG ADD "HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /F /V PATHEXT /T REG_SZ /D "%PATHEXT%;.LNK"
)
:: Setting a temporary dummy variable so setx will broadcast WM_SETTINGCHANGE so the PATH & PATHEXT changes are reflected without needing a restart.
SETX /m DUMMY ""
REG DELETE "HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /F /V DUMMY

:: Starting a new command prompt Window where PATH & PATHEXT are updated, so the user can get started.
CD %userprofile%
START cmd /k "CLS & ECHO Cone is now installed. Use 'cone help' to get started!"
EXIT
