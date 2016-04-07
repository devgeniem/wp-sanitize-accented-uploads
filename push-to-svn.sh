#!/bin/bash
###
# This file is used to automatically push new releases from travis to wordpress.org
# You can also use it in your machine too.
# It checkouts latest tag, changes plugin.php and readme.txt and pushes to svn
###

# CHANGE THESE FOR YOUR PLUGIN
PLUGINNAME="plugin.php"
SVNURL="https://plugins.svn.wordpress.org/wp-sanitize-accented-uploads/"

# This is here to help make the script easier
SVNDIR="svn"

# Get latest git branch
GIT_BRANCH=$(git rev-parse --symbolic-full-name --abbrev-ref HEAD)

# Get latest git tag
GIT_TAG=$(git describe --abbrev=0 --tags)
GIT_RESULT=$?
if [ $GIT_RESULT -eq 0 ]; then
  git checkout $GIT_TAG
  # Replace the readme.txt version and plugin.php with latest git tag
  sed -i.bak "s|* Version:.*|* Version: $GIT_TAG|g" $PLUGINNAME
  sed -i.bak "s|Stable tag:.*|Stable tag: $GIT_TAG|g" readme.txt
  # Remove backup files
  rm $PLUGINNAME.bak readme.txt.bak
else
  exit 1
fi

# Create svn directory if not found
if [ ! -d "$SVNDIR" ]; then
  echo "Checking out wordpress.org svn"
  svn checkout $SVNURL $SVNDIR
  cd $SVNDIR
else
  # If directory existed already update it
  cd $SVNDIR
  svn update
fi


if [ ! -d "$SVNDIR/tags/$GIT_TAG" ]; then
  # Add all files to svn directory
  # Exclude:
  # - tests
  # - composer.json
  # - README.md
  rsync -a --delete --exclude tests --exclude composer.json --exclude phpunit.xml --exclude .gitignore \
           --exclude $SVNDIR --exclude README.md --exclude *.sh --exclude .git \
           ../* trunk/
  svn add trunk --force
  svn ci -m "Synced with git"

  # Git tag -> SVN-tag
  svn copy trunk tags/$GIT_TAG
  svn ci -m "Tagged version $GIT_TAG"
fi

# Return to branch where we were before
git checkout $GIT_BRANCH
