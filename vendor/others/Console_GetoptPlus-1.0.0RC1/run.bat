call pear uninstall  --ignore-errors Console_GetoptPlus
call pear package-validate package2.xml
call pear package package2.xml
call pear install Console_GetoptPlus-1.0.0RC1.tgz