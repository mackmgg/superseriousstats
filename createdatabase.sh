#!/bin/bash

read -p "Prefix: " prefix
read -p "Database: " database
read -p "MySQL username: " user
sed "s/\[prefix\]/$prefix/g" "prefix_database.sql" > prefix_database-$prefix.sql
mysql -p -u $user $database < prefix_database-$prefix.sql
