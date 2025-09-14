# HireMe

## Debugging configuration

The application reads an `app.debug` flag from `config/config.php` (or the
`APP_DEBUG` environment variable). When enabled, uncaught exceptions display the
full message and stack trace. **Ensure deployments set `APP_DEBUG=false` in
production** to avoid exposing sensitive information.

