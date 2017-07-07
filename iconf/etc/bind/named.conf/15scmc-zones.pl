#!/usr/bin/perl -CSDAL

use warnings;
use strict;
use utf8;
use IServ::Conf;
use IServ::DB;

my @domains = IServ::DB::SelectCol "SELECT WebDomain FROM scmc_servers";
my $portal_domain = $conf->{Domain};
my @alias_domains = @{ $conf->{AliasDomains} };

sub is_part_of_domain($)
{
  my $check_domain = shift;
  return 1 if $check_domain =~ /^(.*)\.$portal_domain$/;
  for (@alias_domains)
  {
    return 1 if $check_domain =~ /^(.*)\.$_$/;
  }

  return 0;
}

my %processed;

for (@domains)
{
  # subdomains of portalserver domain are covered by forward.db
  next if is_part_of_domain $_;

  my @domain_elements = split /\./, $_;
  my $scmc_toplevel_domain = pop @domain_elements;
  my $scmc_second_level_domain = pop @domain_elements;
  my $scmc_domain = "$scmc_second_level_domain.$scmc_toplevel_domain";

  next if defined $processed{$scmc_domain};
  
  $processed{$scmc_domain} = 1;
  print "zone \"$scmc_domain\" {\n";
  print "    type master;\n";
  print "    file \"/etc/bind/scmc/db.$scmc_domain\";\n";
  print "};\n\n";

}
