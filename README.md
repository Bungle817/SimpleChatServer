# SimpleChatServer

This is a simple chat server that uses the Fujitsu Interverse 2.0 protocol from c. 1998.

You can access this server using an old version 2.x  WorldsAway client for Windows

<img src="https://repository-images.githubusercontent.com/269489100/c5c75100-a6ca-11ea-9499-17052f61953d">

This should be considered /sample code/ rather than a production ready server - 

No image files are served or supported.  Only the default images built into the client are requeted.

No avatar customisation or persistance is supported; a random avatar is created when you connect.

Only a very limited number of functions are suppoted, namely
- speak
- think
- walk to


Installation

On *nix systems -

Copy all files to a suitable folder.

run  composer i  to install ReactPHP

create a folder ./client

From your installed WorldsAway client, locate the "mag" folder and copy it into the client folder you just created.

type
  php server.php
  
System should fire up and stop at "Listening on tcp://0.0.0.0:17872"

copy connect.iv across to your windows desktop, edit with Notepad or similar.

change 
  serveraddr=127.0.0.1
to whatever the IP address of the machine running the server is.

Save file.  Right-click file, select "Properties", check "Read Only". OK.

Double-click connect.iv, or drag atop wa32.exe, to launch client and connect.
