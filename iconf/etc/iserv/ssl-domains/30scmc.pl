#!/usr/bin/perl -CSDAL

use warnings;
use strict;
use utf8;
use IServ::DB;

my @domains = IServ::DB::SelectCol "SELECT WebDomain FROM scmc_servers";

for (@domains)
{
  print "$_\n";
}
