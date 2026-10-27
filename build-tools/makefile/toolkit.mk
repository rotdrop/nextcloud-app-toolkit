# Common defaults for all fragments in this directory. Included by the other
# fragments, there is no need to include it from the consuming Makefile.
#
# Needs ABSSRCDIR, the absolute path of the consuming app's source directory.
#
# Overridable:
#
# APP_TOOLKIT_DIR       Location of the toolkit, default vendor/rotdrop/nextcloud-app-toolkit.
# APP_TOOLKIT_BUILD_DIR Location of the build tools (this directory's parent).

ABSSRCDIR ?= $(CURDIR)
PHP ?= php
APP_TOOLKIT_DIR ?= $(ABSSRCDIR)/vendor/rotdrop/nextcloud-app-toolkit
APP_TOOLKIT_BUILD_DIR ?= $(APP_TOOLKIT_DIR)/build-tools
