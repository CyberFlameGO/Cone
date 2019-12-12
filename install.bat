@ECHO OFF
TITLE Cone Installer

:: Ensure Administrator Privileges
NET SESSION 1>NUL 2>NUL
IF NOT %errorLevel% == 0 (
	ECHO Requesting elevation...
	powershell "saps -filepath %0 -verb runas"
	EXIT
)

:: Parameters
SET CONE_VERSION=0.12
SET ARCH=x86
IF %processor_architecture% == AMD64 (
	SET ARCH=x64
)

:: Check PHP Installation
CD %ProgramFiles%
WHERE php.exe > php.txt
IF %errorLevel% == 0 (
	SET /p php=<php.txt
	GOTO phpinstalled
)

:: Install PHP
CLS
SET PHP_VERSION=7.4.0
ECHO Downloading PHP %PHP_VERSION%...
powershell -Command "[Net.ServicePointManager]::SecurityProtocol = 'tls12, tls11, tls'; Invoke-WebRequest https://storage.getcone.org/php-%PHP_VERSION%-nts-Win32-vc15-%ARCH%.zip -UseBasicParsing -OutFile %tmp%\php.zip"
IF NOT EXIST "%tmp%\php.zip" (
    ECHO Failed to download PHP.
    PAUSE > NUL
    EXIT
)
ECHO Unpacking PHP...
MKDIR "%tmp%\PHP %PHP_VERSION%"
ECHO Set s = CreateObject("Shell.Application") > tmp.vbs
ECHO s.NameSpace("%tmp%\PHP %PHP_VERSION%").CopyHere(s.NameSpace("%tmp%\php.zip").items) >> tmp.vbs
cscript //nologo tmp.vbs
DEL tmp.vbs
DEL %tmp%\php.zip
MOVE "%tmp%\PHP %PHP_VERSION%" .
ECHO Installing PHP...
SET php=%cd%\PHP %PHP_VERSION%\php.exe
IF NOT EXIST "%php%" (
    ECHO Failed to install PHP.
    PAUSE > NUL
    EXIT
)
SET PATH=%cd%\PHP %PHP_VERSION%\;%PATH%
REG ADD "HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /F /V PATH /T REG_SZ /D "%PATH%"

:phpinstalled
DEL php.txt
:: PHP is now guaranteed to be installed

:: Configure PHP
"%php%" -r "if(php_ini_loaded_file() === false) { $dir = dirname(shell_exec('where php')); file_put_contents($dir.'/php.ini', file_get_contents($dir.'/php.ini-development').\"\nextension_dir=\\\"$dir\\ext\\\"\"); }"
"%php%" -r "file_put_contents(php_ini_loaded_file(), str_replace(';extension=openssl', 'extension=openssl', file_get_contents(php_ini_loaded_file())));"

:: Create Cone Folder
IF NOT EXIST Hell.sh\ MKDIR Hell.sh
CD Hell.sh
IF NOT EXIST Cone\ MKDIR Cone
CD Cone
IF EXIST Cone.zip DEL Cone.zip

:: Download Cone
ECHO Downloading Cone v%CONE_VERSION%...
powershell -Command "[Net.ServicePointManager]::SecurityProtocol = 'tls12, tls11, tls'; Invoke-WebRequest https://github.com/getcone/Cone/archive/v%CONE_VERSION%.zip -UseBasicParsing -OutFile %tmp%\Cone.zip"
IF NOT EXIST "%tmp%\Cone.zip" (
    ECHO Failed to download Cone.
    PAUSE > NUL
    EXIT
)

:: Unpack Cone
IF EXIST _update_ (
	ECHO Updating Cone...
) ELSE (
	ECHO Installing Cone...
)
ECHO Set s = CreateObject("Shell.Application") > tmp.vbs
ECHO s.NameSpace("%tmp%").CopyHere(s.NameSpace("%tmp%\Cone.zip").items) >> tmp.vbs
cscript //nologo tmp.vbs
DEL tmp.vbs
DEL %tmp%\Cone.zip
IF EXIST src\ RMDIR /S /Q src
MOVE %tmp%\Cone-%CONE_VERSION%\src src
IF EXIST cli.php DEL cli.php
MOVE %tmp%\Cone-%CONE_VERSION%\cli.php cli.php
IF NOT EXIST path\ MKDIR path
IF EXIST path\cone.bat DEL path\cone.bat
MOVE %tmp%\Cone-%CONE_VERSION%\cone.bat path\cone.bat
IF EXIST path\cone DEL path\cone
ECHO #!/bin/bash > path\cone
ECHO cone.bat "$@" >> path\cone
RMDIR /S /Q %tmp%\Cone-%CONE_VERSION%

:: Create Start Menu Folder
SET install_dir=%cd%
CD "%ProgramData%\Microsoft\Windows\Start Menu\Programs"
IF NOT EXIST Hell.sh\ MKDIR Hell.sh
CD Hell.sh
IF NOT EXIST Cone\ MKDIR Cone
CD %install_dir%

IF EXIST _update_ (
    :: Finish Update
	DEL _update_
	START cmd /k "CLS & DEL %tmp%\Cone_update.bat & cone update"
	EXIT
)

:: Add Cone to PATH
REG ADD "HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /F /V PATH /T REG_SZ /D "%cd%\path\;%PATH%"
SETX /m DUMMY ""
REG DELETE "HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /F /V DUMMY

:: Finish Install
"%php%" cli.php update --post-install
ECHO Cone is now installed.
ECHO Get started by opening Command Prompt (as Administrator) and typing "cone help".
PAUSE > NUL
