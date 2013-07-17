Websocket Framework v0.0.2
======================================

<img src="https://raw.github.com/wmcmurray/websocket-framework/master/screenshots/server.gif" width="300" align="right" title="Server cmd prompt">
This project provide a way to easily create PHP websocket servers and JavaScript clients.
You won't have to deal with socket listening, handshaking, data unmasking and such, it's all done by the framework.

**All you have to do is : create what you have in mind !**

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
* [How to use : general](https://github.com/wmcmurray/websocket-framework/wiki/How-to-use)
* [How to use : remote admin](https://github.com/wmcmurray/websocket-framework/wiki/Remote-admin)
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
			<img src="https://raw.github.com/wmcmurray/websocket-framework/master/screenshots/demo-helloworld.gif" width="210">
		</td>
		<td>
			<img src="https://raw.github.com/wmcmurray/websocket-framework/master/screenshots/demo-admin.gif" width="210">
		</td>
		<td>
			<img src="https://raw.github.com/wmcmurray/websocket-framework/master/screenshots/demo-chat.gif" width="210">
		</td>
	</tr>
</table>

TODOs list
-------------------------
- [x] Create an Admin Control Panel demo
- [x] Create an Hello World demo
- [x] Add a raw transport layer (raw_send()) for people who wants full control over optimisation
- [x] Add possibility to create custom admin commands
- [x] Add an helper to save and retrieve data in text files
- [/] Create a simple game demo
- [ ] Create a realtime website demo
- [ ] Create an online radio
- [ ] Add an admin command to buffer server errors and retrieve them via remote client
- [ ] Add a way to ban a client definitively with the Admin API

