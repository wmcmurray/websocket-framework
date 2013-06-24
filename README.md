Websocket Framework v0.0.1
======================================

<img src="https://raw.github.com/wmcmurray/websocket-framework/master/screenshot-server.gif" width="300" align="right" title="Server cmd prompt">
This project provide a way to easily create websocket servers as well as clients.
You won't have to deal with socket listening, handshaking, data unmasking and such, it's all done by the framework.

**All you'll have to do is : create what you have in mind !**

**Licence :** GNU General Public License, version 3.0 (GPLv3)

**Please note :** this is an early version so the API is likely to change eventually.


Features
-------------------------
* **Easy** to use API
* **Only 10 lines of code** to make a working server
* **Remote control** of servers via a JavaScript client (kick clients, shutdown/reboot server, etc.)
* **Multiple servers** architechture based on the same core
* **A groups system** enabling internal communications between group clients (like a chatrooms in a chat)
* **Connected clients limit** to prevent your servers from exploding


Ressources for developers
-------------------------
* [How to use](https://github.com/wmcmurray/websocket-framework/wiki/How-to-use)
* [API reference : server](https://github.com/wmcmurray/websocket-framework/wiki/API-reference-:-server)
* [API reference : client](https://github.com/wmcmurray/websocket-framework/wiki/API-reference-:-client)


Demos included
-------------------------
<table>
	<tr>
		<th>Hello world ! server</th>
		<th>Administrator control panel</th>
		<th>Basic Chat server</th>
	</tr>
	<tr>
		<td>
			<img src="https://raw.github.com/wmcmurray/websocket-framework/master/public/helloworld/images/screenshot.gif" width="210">
		</td>
		<td>
			<img src="https://raw.github.com/wmcmurray/websocket-framework/master/public/admin/images/screenshot.gif" width="210">
		</td>
		<td>
			<img src="https://raw.github.com/wmcmurray/websocket-framework/master/public/chat/images/screenshot.gif" width="210">
		</td>
	</tr>
</table>

TODOs list
-------------------------
- [x] Clean this README.md file
- [x] Clean demos code and integrate twitter bootstrap
- [x] Create an Admin Control Panel demo
- [x] Create an Hello World demo
- [x] Add possibility to set different output modes in each server instances instead of common config file
- [x] Add more details about APIs methods in README.md file (client side)
- [/] Clean the core PHP code
- [ ] Create a realtime website demo
- [ ] Create a simple game demo
- [ ] Add a raw transport layer (raw_send()) for people who wants full optimisation
- [ ] Add methods to save and retrieve data in text files
- [ ] Add an admin command to buffer server errors and retrieve them via remote client
- [x] Set PHP errors reporting to E_ALL only if -debug options is set
- [ ] Find a way to redefine the server class when server is rebooted remotely by an admin
- [ ] Add a way to ban a client definitively with the Admin API

