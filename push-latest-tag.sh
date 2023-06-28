#!/bin/bash

#USAGE: AWS_ACCESS_KEY_ID=ABCD AWS_SECRET_ACCESS_KEY=EF1234 ./push-latest-tag.sh

echo "Updating local repo..."
git pull

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
LATEST_TAG=$(git describe --tags $(git rev-list --tags --max-count=1))

CURRENT_DIR=${PWD##*/}

git checkout $LATEST_TAG --quiet
git archive --prefix "$CURRENT_DIR/" -o "$LATEST_TAG.zip" HEAD
git checkout $CURRENT_BRANCH --quiet

echo "Uploading to S3: $LATEST_TAG.zip"
#aws s3 cp $LATEST_TAG.zip s3://cru-wordpress-plugins-repository/$CURRENT_DIR/
aws s3 cp $LATEST_TAG.zip s3://cru-wordpress-plugins-repository/bocs/

rm $LATEST_TAG.zip