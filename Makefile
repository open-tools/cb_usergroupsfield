BASE=usergroupsfield
PLUGINTYPE=plug
ZIPBASE=opentools_cb2
VERSION=2.0.1

PLUGINFILES=cb.$(BASE).php cb.$(BASE).xml

TRANSLATIONS=
# $(call wildcard,language/en*/*.plg_$(PLUGINTYPE)_$(BASE).*ini) 
INDEXFILES=index.html
ELEMENTS=$(call wildcard,elements/*.php) 
ZIPFILE=plug_$(ZIPBASE)_$(BASE)_v$(VERSION).zip

all: zip

zip: $(PLUGINFILES) $(TRANSLATIONS) $(ADVANCEDFILES)
	@echo "Packing all files into distribution file $(ZIPFILE):"
	@zip -r $(ZIPFILE) $(PLUGINFILES) $(TRANSLATIONS) $(ELEMENTS) $(INDEXFILES)

clean:
	rm -f $(ZIPFILE) $(ZIPFILE_ADV)
