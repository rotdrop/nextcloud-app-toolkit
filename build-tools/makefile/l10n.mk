# Makefile fragment which generates the "string catalogue" of the toolkit
# for the consuming app. The Nextcloud translation tool only scans the app's
# git checkout, not vendor/, so the catalogue has to be committed to the app.
#
# Usage in the consuming Makefile (ABSSRCDIR is the app's source directory):
#
# ABSSRCDIR = $(CURDIR)
# include $(ABSSRCDIR)/vendor/rotdrop/nextcloud-app-toolkit/build-tools/makefile/l10n.mk
#
# optional overrides, before the include:
#
# APP_TOOLKIT_DIR = $(ABSSRCDIR)/vendor/rotdrop/nextcloud-app-toolkit
# APP_TOOLKIT_L10N_DEST = $(ABSSRCDIR)/l10n-sources/toolkit-strings.php
# PHP = php

include $(dir $(lastword $(MAKEFILE_LIST)))toolkit.mk

APP_TOOLKIT_L10N_DEST ?= $(ABSSRCDIR)/l10n-sources/toolkit-strings.php

#@@ Generate the toolkit's string catalogue for the translation tool, commit the result.
toolkit-l10n:
	$(PHP) $(APP_TOOLKIT_BUILD_DIR)/scripts/extract-l10n.php $(APP_TOOLKIT_L10N_DEST) $(APP_TOOLKIT_DIR)/src
.PHONY: toolkit-l10n
