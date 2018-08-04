# SCMC Apache module
package Stsbl::IServ::SCMC::Apache;

use warnings;
use strict;
use File::Slurp::Unicode;
use IServ::Conf;
use IServ::DB;
use JSON;

BEGIN
{
  use Exporter;
  our @ISA = qw(Exporter);
  our @EXPORT = qw(gen_blocked_conf);
}

my $fn_cfg = "/var/lib/stsbl/scmc/cfg/room-mode.json";
my $json = JSON->new->utf8->allow_nonref;

sub gen_blocked_conf($)
{
  my $line_prefix = shift;
  my $content = "";
  my $invert;

  $line_prefix = "" if not defined $line_prefix;

  if (-f $fn_cfg)
  {
    $content = join "", read_file $fn_cfg;
  }

  eval
  {
    my $decoded = $json->decode($content);

    if ($decoded->{invert} eq "true")
    {
      $invert = 1;
    } else
    {
      $invert = 0;
    }
  };

  # fill default on decoding error
  $invert = 1 if $@;

  my $sql;

  if (not $invert)
  {
    $sql = "SELECT h.IP FROM hosts h WHERE NOT EXISTS (SELECT 1 FROM scmc_rooms r WHERE r.room_id = h.room_id) ORDER BY h.IP";
  } else
  {
    $sql = "SELECT h.IP FROM hosts h WHERE EXISTS (SELECT 1 FROM scmc_rooms r WHERE r.room:id = h.room_id) ORDER BY h.IP";
  }

  my $regex = "^(" . join("|", IServ::DB::SelectCol $sql) . ")\$";
  my $blocked_cfg = "";
  
  if (not $regex eq "^()\$")
  {
    $blocked_cfg = "${line_prefix}# Only private IP sections\n";

    my @lan = @{ $conf->{LAN} };
 
    for my $range (@lan)
    {
      $blocked_cfg .= "${line_prefix}RewriteCond expr \"-R '$range'\"\n";
      $blocked_cfg .= "${line_prefix}RewriteCond %{REMOTE_ADDR} $regex\n".
          "${line_prefix}RewriteCond %{REQUEST_URI} ^/WZeugnis\n".
	  "${line_prefix}RewriteRule (.*) /public/scmc/block [R=307,L]\n\n"
      ;
    }
  }

  return $blocked_cfg;
}

1;
