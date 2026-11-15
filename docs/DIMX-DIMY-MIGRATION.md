Migration of old code written for DimensioneX should be easily ported to DImensioneY with little or no modification.

Here is a migration guide for running old .DXW world on DimensioneY:

1) NewRoom instruction has been turned to a one-parameter function.

old code:

Dim $AGENT.id + "_myroom"
NewRoom roomid
roomid.name = "Master Room"
	
	
new code:
		
Dim roomid =	NewRoom("Master Room")