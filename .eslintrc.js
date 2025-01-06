module.exports = {
  globals: {
    bocs_add_to_cart: 'writable'
  },
  rules: {
    'no-unused-vars': ['error', {
      'vars': 'all',
      'varsIgnorePattern': 'bocs_add_to_cart'
    }]
  }
} 