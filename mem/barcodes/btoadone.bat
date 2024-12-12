
::       SSSSS     AA    MM   MM  PPPPP   L       EEEEEE
::      S     S   A  A   M M M M  P    P  L       E
::      S        A    A  M M M M  P    P  L       E
::       SSSSS   AAAAAA  M  M  M  PPPPP   L       EEEE
::            S  A    A  M  M  M  P       L       E
::      S     S  A    A  M     M  P       L       E
::       SSSSS   A    A  M     M  P       LLLLLL  EEEEEE

@echo off
:: NOTE - PostProcess.Py inserts the below indicated env variables
::  at the top of the script, just before running it

::
:: BtoaDone.Cmd - Vista PE Version
::
:: This script is called by the main Vista PE process code, after processing
::  the success/failure of the system
:: This script serves as a hook for the ODM to include logic to allow for
::  the ODM's own pass/fail reporting to whatever media/resource desired.
::
:: The main BTOA process passes in the pass/fail state as the first command
:: line parameter (%1), valid values are "PASS" or "FAIL", upper case only..
::
:: Upon entry, the following ENV Variables are available. They are added
::  dynamically to the top of this batch file, just prior to running it.
::  This also serves to provide a little debug, in the file, as to what we
::  passed in as the environment:
::  %UP_DL% - Contains the Drive Letter and a ':' (colon) of the Utility
::       Partition.. There is __NO__ trailing back-slash.
::       In some scenarios, there is NO UP in the system.
::  %LOCALDRIVE% - Contains the path to the Manufacturing Media, the
::       Drive:Directory where the BTOA process files are stored.
::       NOTE - This variable value DOES NOT contain a trailing '\'.
::       ALSO, This could be the RAMDRIVE copy of the MFGMEDIA directory,
::       if the system has already been cleaned.
::  %BTOA_LOG% - Contains the path to the LOG file on the Manufacturing
::       Media.  This log file can be used to record informational messages.
::       Log entries should be in the form:
::         echo [BTOADONE] My sample info log entry>> %BTOA_LOG%
::  %BTOA_ERR% - Contains the path to the Error log file on the
::       Manufacturing Media. This log is used to record error information.
::       Log entries should be in the form:
::         echo [BTOADONE] ERROR - My sample error log entry>> %BTOA_ERR%
::  %TIME_DIR% - Contains a string that we use for the logging functionality
::       ONLY, to create the directory under the BTOALOGS Directory on the
::       LOG server. I gues it could be use by others. Format is YYMMDDHH.MM
::  %BTOA_DEBUG% - Indicates if BTOA DEBUG Mode is enabled. This can be
::       empty/0, 1, 2, 3. We use 3 here, so as to isolate it from other
::       debugging
::  %SNUM% - The Serial Number/Service Tag of the system

echo [BTOADONE.CMD] Running With Status Parameter: %1

:: if this is 0 @ Label :Exit, we will initiate a success exit
:: if this is !0 @ Label :Exit, we will initiate a failure exit
set RESULT=0

:: Jump to the appropriate pass or fail block
if "%1" == "PASS" goto :Success
if "%1" == "FAIL" goto :Failure
goto :FailParm



::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
:: PASS Block                                                               ::
::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::
:Success
:: NOTE - Set 'RESULT' to any non-zero value to fail the BTOA process.
::  FYI - 'RESULT' defaults To '0' above
:: PASS reporting start





:: Add code here, to do any PASS processing you might need to do.

:: Below is a sample of some actions ARB might do

::Check HDD Wipe has been Completed
if exist %LOCALDRIVE%\mapnet.bat call %LOCALDRIVE%\mapnet.bat
if NOT EXIST %NET_DRIVE%\LOGS\%SNUM%\hdd_wipe_comp.flg ECHO No Hard Drive WIpe Flag Has been Set Cannot Continue
IF NOT EXIST %NET_DRIVE%\LOGS\%SNUM%\hdd_wipe_comp.flg GOTO :Failure

%NET_DRIVE%

::Set the system time if needed - a supporting script -- example call of a script to do this - FICORE can do this too - some regions not accurate
::if exist %NET_DRIVE%\COMMON\BTOA\conf\SETDATE.BAT call %NET_DRIVE%\COMMON\BTOA\conf\SETDATE.BAT

::Copy any Pass Logs to from UUT to Logs folder per service tag if needed - FICORE will flush to MFGMEDIA server but some additional files may be wanted  
::cd LOGS
::if not exist %snum% md %snum%
::cd %snum%
::if not exist BTOALOG md BTOALOG
::copy %LOCALDRIVE%\*.* %NET_DRIVE%\LOGS\%SNUM%\BTOALOG /Y


:::Copy the keyout.xml for Win  OS installs - to Spool directory where it can be processed by API to upload to the LKM
%systemdrive%
cd mfgmedia
copy *.* %NET_DRIVE%\LOGS\%SNUM%\BTOALOG /Y
if exist KeyOut.xml rename KeyOut.xml KeyOut_%snum%.xml
copy keyout_%snum%.xml %NET_DRIVE%\KEYOUT\SPOOL /Y


:::Any other actions needed per site can be added here in the pass section - e.g. Burn Rack Monitor/ Database Upload trigger / Happy face Bitmap whatever.

::PASS reporting end

goto :Exit
:: END PASS Block:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::



::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
:: FAIL Block                                                               ::
::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::
:Failure

:: Setting RESULT is not required to fail the BTOA process as we are already
:: on the way to failing the process.
::FAIL reporting start



:: Add code here, to do any FAIL processing you might need to do.

::As above add what failure routines are needed here on failure - 

::Example - Setup for Retool Enablement - the failure screen has an option for retool - this is enabled per site and may not be wanted only for pilot env
if not exist %systemdrive%\retools md if not exist %systemdrive%\retools
if exist %DiagMFGMediaLocation%\ficretools.zip 7za x -y -o%systemdrive%\retools %DiagMFGMediaLocation%\ficretools.zip 


::Example - Copy failure logs to a location 

::Example - Set Flags and set/check system time and date for accurate logging if needed per site

::FAIL reporting end

goto :Exit
:: END FAIL Block:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::


:: ====================================================================
:: Invalid parameter passed into BTOADONE.CMD
:FailParm
echo [BTOADONE.CMD] Invalid status parameter passed in: [%1]
echo [BTOADONE.CMD] EXPECTED PARM_1 = PASS or FAIL
SET RESULT=1
goto :Exit
:: ====================================================================

:Exit
::       SSSSS     AA    MM   MM  PPPPP   L       EEEEEE
::      S     S   A  A   M M M M  P    P  L       E
::      S        A    A  M M M M  P    P  L       E
::       SSSSS   AAAAAA  M  M  M  PPPPP   L       EEEE
::            S  A    A  M  M  M  P       L       E
::      S     S  A    A  M     M  P       L       E
::       SSSSS   A    A  M     M  P       LLLLLL  EEEEEE

:: Set return code errorlevel; 0 = pass, 1 = failure
if "%RESULT%" == "0" CD > NUL
:: unless 'WE_FAILED.EXE' exists (and it should not),
::  this will set a non-zero errorlevel
if not "%RESULT%" == "0" WE_FAILED 2> NUL
:: DO NOT PUT ANYTHING AFTER THE ABOVE 2 LINES
