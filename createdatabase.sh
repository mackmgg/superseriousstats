#!/bin/bash

sed "s/\[prefix\]/$1/g" "prefix_database.sql" > prefix_database-$1.sql

