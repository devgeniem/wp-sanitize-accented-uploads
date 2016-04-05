#!/bin/bash
#Check these variables accordingly to your plugin

PLUGINNAME="plugin.php" #CHANGE THIS LINE FOR YOUR PLUGIN

SVNDIR="svn"
SVNURL="https://plugins.svn.wordpress.org/wp-sanitize-accented-uploads/"

git status
# warn if there are uncommitted changes!

git pull origin master

# Check version in readme.txt is the same as plugin file
NEWVERSION1=`grep "^Stable tag" readme.txt | awk -F' ' '{print $3}'`
echo "readme.txt version: $NEWVERSION1"
NEWVERSION2=`grep "Version" $PLUGINNAME | awk -F' ' '{print $3}'`
echo "$PLUGINNAME version: $NEWVERSION2"
if [ "$NEWVERSION1" != "$NEWVERSION2" ]; then echo "Versions don't
match. Exiting...."; exit 1; fi
echo "Versions match in readme.txt and PHP file. Let's proceed..."

# sed to bump version?
# git commit -am "Bumped version up"

if GIT_DIR=/path/to/repo/.git git rev-parse $1 >/dev/null 2>&1
then
  echo "Found tag version already"
else
  git tag -f -s $NEWVERSION1 -m "Tagged to match version in SVN"
fi

echo "Pushing git master to origin, with tags"
git push origin HEAD
git push origin HEAD --tags --force

# Creat svn directory if not found
if [ ! -d "$SVNDIR" ]; then
  echo "Checking out wordpress.org svn"
  svn checkout $SVNURL $SVNDIR --trust-server-cert
fi

cd $SVNDIR

svn update

cp -r ../* trunk/
svn add trunk --force
svn ci -m "Synced with git"

# Git tag -> SVN-tag
svn copy trunk tags/$NEWVERSION1
svn ci -m "Tagged version $NEWVERSION1"
