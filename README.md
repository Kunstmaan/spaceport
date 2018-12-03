## Installation

```
git clone https://github.com/Kunstmaan/spaceport.git /some/path/spaceport
chmod +x /some/path/spaceport/spaceport
ln -s /some/path/spaceport/spaceport /usr/local/bin/spaceport   
```

## Install 

## BG-sync White- and Black-listing folders for 2 way sync

Under the bg-sync yaml it is possible to configure a multitude of unison configurations. 
You can find all possible unison configuration on this website.
https://www.cis.upenn.edu/~bcpierce/unison/download/releases/stable/unison-manual.html
Unison configurations need to be piped in the SYNC_EXTRA_UNISON_PROFILE_OPTS variable. 

Most commonly you will only want to use the ignore and ignorenot configurations as below in the example.
It is best to ignore as much as you can to speed up the container.

```yml
    bg-sync:
        image: cweagans/bg-sync
        environment:
            SYNC_EXTRA_UNISON_PROFILE_OPTS: |
              ignore = Name vendor/
              ignorenot = Name vendor/kunstmaan
```
