#!/bin/bash
###
# This file is used to automatically push new releases from travis to wordpress.org
###

# CHANGE THESE FOR YOUR PLUGIN
PLUGINNAME="plugin.php"
SVNURL="https://plugins.svn.wordpress.org/wp-sanitize-accented-uploads/"

# This is here to help make the script easier
SVNDIR="svn"

# Get latest git
GIT_TAG=$(git describe --abbrev=0 --tags)
GIT_RESULT=$?
if [ $GIT_RESULT -eq 0 ]; then
  # Replace the readme.txt version and plugin.php with latest git tag
  sed "s|* Version:.*|* Version: $GIT_TAG|g" $PLUGINNAME
  sed "s|Stable tag:.*|Stable tag: $GIT_TAG|g" readme.txt
fi

# Create svn directory if not found
if [ ! -d "$SVNDIR" ]; then
  echo "Checking out wordpress.org svn"
  svn checkout $SVNURL $SVNDIR
else
  # If directory existed already update it
  svn update $SVNDIR
fi

# Add all files to svn directory
# Exclude:
# - tests
# - composer.json
# - README.md
rsync -a --delete --exclude tests --exclude composer.json --exclude phpunit.xml \
         --exclude $SVNDIR --exclude README.md --exclude push-to-svn.sh \
         . $SVNDIR/trunk/
svn add trunk --force
svn ci -m "Synced with git"

# Git tag -> SVN-tag
svn copy trunk tags/$GIT_TAG
svn ci -m "Tagged version $GIT_TAG"
