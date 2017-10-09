#!/usr/bin/perl -CSDAL

use warnings;
use strict;
use utf8;
use IServ::DB;

print "$_\n"
  for IServ::DB::SelectCol "SELECT WebDomain FROM scmc_servers ORDER BY WebDomain";
