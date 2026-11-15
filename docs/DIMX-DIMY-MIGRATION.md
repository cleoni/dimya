Migration of old code written for DimensioneX should be easily ported to DImensioneY with little or no modification.

Here is a migration guide for running old .DXW world on DimensioneY:

1) NewRoom instruction has been turned to a one-parameter function.

old code:

	Dim $AGENT.id + "_myroom"
	NewRoom roomid
	roomid.name = "Master Room"
	
	
new code:
		
	Dim roomid =	NewRoom("Master Room")

2) Clusters dropped.

The clusters feature has been dropped. MoveOutside instruction has been dropped too.

Recommended fix: 

	If your world was based on clusters, then move all game objects and scripts in just one DXW World.

3) Journal instruction has not been implemented yet.

It is possible that it will be re-introduced, but please comment it out.

4) Ban instruction has not been implemented yet.

It is possible that it will be re-introduced, but please comment it out.

5) SendPage istruction has been dropped.

If needed, just replace it with a call to HttpFetch()


Please note:

DimensioneY game engine is still under development and testing. A few features such as world attributes like HTMLCOUNTER may be ignored. Their support will be added soon, in the meantime you are invited to notify us via the project's issue link:
https://github.com/cleoni/dimya/issues