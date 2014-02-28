#!/bin/sh

g++ pic2linesjson.cpp -o pic2linesjson `env PKG_CONFIG_PATH=/usr/local/lib/pkgconfig/ pkg-config --cflags --libs opencv`
