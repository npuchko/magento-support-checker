# Installation
1. Have magento-cloud installed and configured with API TOKEN.
2. Clone this repo into folder

3. Install shortcut script
```shell

sudo ln -s {full_patch_to_repo}/magento-support-checker /usr/local/bin/msc
```

# Usage
## Interactive
```shell
msc
```
## Non-interactive
```shell
msc {cloud-project-id} {cloud-project-env} {check-name}
# example
msc ko32g3ggweggr staging advanced_reporting
```

## List checks:
- Advanced Reporting
```shell
msc ko32g3ggweggr staging advanced_reporting
```

- Product recommendations:
```shell
msc ko32g3ggweggr staging product_recommendations
```

## Using without Magento Cloud
1. Copy phar script into {MAGENTO_ROOT_DIR}/var directory
2. Run php script
```bash
# all checks
php magento_support_checker.phar all
# product recommendations only
php magento_support_checker.phar product_recommendations
# advanced reporting only
php magento_support_checker.phar advanced_reporting
```




# Example report:
```shell
Check group: advanced_reporting with total 8 rules'
[1/8] Is Enabled: OK
[2/8] Multiple Currency: OK
[3/8] Cron in DB settings: [{"scope":"default","scope_id":"0","value":"00 02 * * *"}]
Cron jobs in DB not found
[4/8] Token in settings: OK
[5/8] Last generated report in flag: 0 days ago. (2022-06-02 07:00:54) File URL: https://example.com/media/analytics/23gergerggregfbd4tbregre/data.tgz
OK
[6/8] Escaped quotes in db: OK
[7/8] Check unexisting stores: OK
[8/8] Generated report URL: https://advancedreporting.rjmetrics.com/report?otp=vwewrgerg4fergergreg-ergergergergrgeergregerg


```


# Contribution

### To build phar file
```bash
cd build && ./build.sh
```

### To add check
1. Create new class in src/MagentoSupport/SupportChecker/Check/{check_folder}
2. Add class into src/main.php  -> $checksList variable