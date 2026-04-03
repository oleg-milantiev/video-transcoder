const crypto = require('crypto');

function pad(value) {
  return String(value).padStart(2, '0');
}

function formatDateSuffix(date = new Date()) {
  return `${date.getFullYear()}${pad(date.getMonth() + 1)}${pad(date.getDate())}`;
}

function randomAlphaNumeric(length = 8) {
  const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  const bytes = crypto.randomBytes(length * 2);
  let result = '';

  for (const byte of bytes) {
    result += alphabet[byte % alphabet.length];
    if (result.length === length) {
      break;
    }
  }

  if (result.length < length) {
    return result + randomAlphaNumeric(length - result.length);
  }

  return result;
}

function buildRunContext(env = process.env, now = new Date()) {
  const dateSuffix = env.PROD_DATE_SUFFIX || formatDateSuffix(now);
  const userLocalPart = env.PROD_USER_LOCAL_PART || `prod-${dateSuffix}`;
  const userDomain = env.PROD_USER_DOMAIN || 'example.test';
  const userEmail = env.PROD_USER_EMAIL || `${userLocalPart}@${userDomain}`;
  const userPassword = env.PROD_USER_PASSWORD || randomAlphaNumeric(8);
  const videoBaseName = env.PROD_VIDEO_BASENAME || userLocalPart;

  return {
    dateSuffix,
    userLocalPart,
    userDomain,
    userEmail,
    userPassword,
    sourceVideoFileName: env.PROD_SOURCE_VIDEO || '2022_10_04_Two_Maxes.mp4',
    videoBaseName,
    uploadFileName: `${videoBaseName}.mp4`,
  };
}

module.exports = {
  formatDateSuffix,
  randomAlphaNumeric,
  buildRunContext,
};

