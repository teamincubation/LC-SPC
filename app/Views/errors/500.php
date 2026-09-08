<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 - Server Error | LC-SPC</title>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background-color: #f8fafc;
      color: #0f172a;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      padding: 1.5rem;
    }
    .error-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 3rem 2rem;
      max-width: 480px;
      width: 100%;
      text-align: center;
      box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    }
    .error-code {
      font-size: 3.5rem;
      font-weight: 800;
      color: #dc2626;
      line-height: 1;
      margin-bottom: 1rem;
    }
    h1 {
      font-size: 1.35rem;
      margin-bottom: 0.75rem;
    }
    p {
      color: #64748b;
      font-size: 0.95rem;
      line-height: 1.5;
      margin-bottom: 1.5rem;
    }
    a {
      display: inline-block;
      background: #1e3a8a;
      color: #ffffff;
      text-decoration: none;
      padding: 0.6rem 1.25rem;
      border-radius: 4px;
      font-size: 0.9rem;
      font-weight: 500;
    }
    a:hover {
      background: #172554;
    }
  </style>
</head>
<body>
  <div class="error-card">
    <div class="error-code">500</div>
    <h1>Internal Server Error</h1>
    <p>
      An unexpected error occurred while processing your request. 
      Our technical team has been notified and the incident has been logged.
    </p>
    <a href="javascript:location.reload()">Refresh Page</a>
  </div>
</body>
</html>
