FROM python:3.12-slim

WORKDIR /app

# Install dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy source code and the model
COPY . .

# Expose port 5000
EXPOSE 5000

# Start Flask with Gunicorn for production
CMD ["gunicorn", "-b", "0.0.0.0:5000", "--workers=2", "app:app"]
