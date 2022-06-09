 1. Have magento-cloud installed and configured with API TOKEN.

2. Run command
```shell

sudo ln -s /Users/npuchko/www/tools/advanced-reporting-check/magento-support-checker /usr/local/bin/msc
```

3. Usage
Interactive
```shell
msc
```
Non-interactive
```shell
msc cloud-project-id cloud-project-env
# example
msc ko32g3ggweggr staging
```

To check product recommendations:
```shell
msc ko32g3ggweggr staging product_recommendations
```

Example report:
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