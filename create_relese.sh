#!/bin/bash

# This name *must* match with the git folder
PLUGIN_NAME=ps_apurata

(
	mkdir output;
	mkdir output/${PLUGIN_NAME};
	cp -r * output/${PLUGIN_NAME};
	cd output && zip -r ${PLUGIN_NAME}.zip ${PLUGIN_NAME}/ -x output/* && rm -r ${PLUGIN_NAME};
)