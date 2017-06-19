# SCMC Library

package Stsbl::IServ::SCMC;
use warnings;
use utf8;
use strict;
use Fcntl ":flock";
use IServ::Act;

BEGIN
{
  use Exporter;
  our @ISA = qw(Exporter);
  our @EXPORT = qw(getusrnam legacy_crypt);
}

my $fn_master_passwd = "/etc/stsbl/scmcmasterpasswd";
my $fn_user_passwd = "/etc/stsbl/scmcpasswd";

sub getusrnam($)
{
   my ($act) = @_;
   open my $fh, "<", $fn_user_passwd;
   while (<$fh>)
   {
     chomp;
     my @f = split /:/;
     my $uid = $f[0];
     # we need to store the uid instead of the account name in scmcpasswd, 
     # as we cannot handle account name updates :/ .
     my $name = getpwuid $uid;
     $f[0] = $name;
     return @f if $name eq $act;
   }
}

sub legacy_crypt($$)
{
  my ($pwd, $salt) = @_;
  my $crypt;
  # calculate legacy hash
  eval
  {
    local $ENV{SCMC_SESSIONPW} = $pwd;
    local $ENV{SCMC_SESSIONSALT} = $salt;
    $crypt = qx(/usr/lib/iserv/scmc_php_hash);
    chomp $crypt;
  };
  
  die "calculating of php hash failed: $@" if $@;
  
  return $crypt if defined $crypt;
}

sub MasterPasswdEnc($)
{
  my $pw = shift;
  open my $fh, ">", $fn_master_passwd or die "Couldn't open file $fn_master_passwd: $!\n";
  flock $fh, LOCK_EX or die "Couldn't lock file $fn_master_passwd: $!\n";
  print $fh "$pw\n";
  close $fh or die "Couldn't write file $fn_master_passwd: $!\n";
}

sub MasterPasswd($)
{
  my $pw = shift;
  $pw = IServ::Act::crypt_auto $pw if defined $pw;
  MasterPasswdEnc $pw;
}

sub UserPasswdEnc($$)
{
  my ($act, $pw) = @_;
  my %out;

  open my $fh, "<", $fn_user_passwd;
  while (<$fh>)
  {
    my @line = split /:/;
    if (my (undef, undef, $uid) = getpwuid $line[0])
    {
      # skip account which should get the new password
      unless ($line[0] eq $act)
      {
        # we need to have uid in passwd file, as we cannot handle account name updates :/ .
        $line[0] = $uid;
        $out{$line[0]} = \@line;
      }
    } else
    {
      print STDERR "user $line[0] seems to does not exists!\n".
        "password of user $line[0] will not transferred to new passwd file!\n";
    }
  }
  close $fh;

  open $fh, ">", $fn_user_passwd;
  flock $fh, LOCK_EX or die "Couldn't lock file $fn_user_passwd: $!\n";
  foreach my $index (keys %out)
  {
    my $line = join ":", @{ $out{$index} };
    print $fh $line;
  }

  my (undef, undef, $uid) = getpwnam $act;
  # add new password at bottom
  print $fh "$uid:$pw\n";
  close $fh;
}

sub UserPasswd($$)
{
  my ($act, $pw) = @_;
  $pw = IServ::Act::crypt_auto $pw if defined $pw;
  UserPasswdEnc $act, $pw;
}
1;
