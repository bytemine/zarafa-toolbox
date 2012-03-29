#!/bin/bash
# easily download zarafa packages via cli
# just replace $USER and $PASSWORD with your data
# script gets called like this
# ./download-zarafa.sh https://download.zarafa.com/supported/final/7.0/7.0.6-32752/zcp-7.0.6-32752-debian-6.0-x86_64-supported.tar.gz
USER=portal-user
PASSWORD=portal-password

wget --user=$USER --password=$PASSWORD --no-check-certificate $1
