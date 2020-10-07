#---------------------------
# This script generates a new pmpro.pot file for use in translations.
# To generate a new pmpro-cancel-on-next-payment-date.pot, cd to the main /pmpro-cancel-on-next-payment-date/ directory,
# then execute `languages/gettext.sh` from the command line.
# then fix the header info (helps to have the old pmpro.pot open before running script above)
# then execute `cp languages/pmpro-cancel-on-next-payment-date.pot languages/pmpro-cancel-on-next-payment-date.po` to copy the .pot to .po
# then execute `msgfmt languages/pmpro-cancel-on-next-payment-date.po --output-file languages/pmpro-cancel-on-next-payment-date.mo` to generate the .mo
#---------------------------
echo "Updating pmpro-cancel-on-next-payment-date.pot... "
xgettext -j -o languages/pmpro-cancel-on-next-payment-date.pot \
--default-domain=pmpro-cancel-on-next-payment-date \
--language=PHP \
--keyword=_ \
--keyword=__ \
--keyword=_e \
--keyword=_ex \
--keyword=_n \
--keyword=_x \
--sort-by-file \
--package-version=1.0 \
--msgid-bugs-address="info@paidmembershipspro.com" \
$(find . -name "*.php")
echo "Done!"
