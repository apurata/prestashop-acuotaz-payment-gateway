#!/bin/bash

# This name *must* match with the git folder
PLUGIN_NAME=ps_apurata

(
	cd ..;
	mkdir ../output;
	mkdir ../output/${PLUGIN_NAME};
	cp -r * ../output/${PLUGIN_NAME};
	cd ../output && zip -r ${PLUGIN_NAME}.zip ${PLUGIN_NAME}/ -x ${PLUGIN_NAME}/create_release/\* -x ${PLUGIN_NAME}/images/\*;
	rm -r ${PLUGIN_NAME};
	
)