export function returningToReview() {
  return new URLSearchParams(window.location.search).get('returnTo') === 'review'
}

export function setupExitPath() {
  return returningToReview() ? '/setup/review' : '/setup'
}
